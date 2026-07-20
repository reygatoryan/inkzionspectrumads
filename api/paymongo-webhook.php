<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db-config.php';

function getWebhookSecret() {
    $result = $GLOBALS['conn']->query("SELECT settings FROM shipping_settings WHERE section_key='shipping' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $settings = json_decode($row['settings'], true);
        return $settings['payment']['paymongo']['webhook_secret'] ?? '';
    }
    return '';
}

function verifyWebhookSignature($payload, $signatureHeader) {
    $secret = getWebhookSecret();
    if (empty($secret)) {
        return false;
    }

    $parts = explode(',', $signatureHeader);
    $timestamp = '';
    $receivedSignature = '';

    foreach ($parts as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) === 2) {
            $k = trim($kv[0]);
            $v = trim($kv[1]);
            if ($k === 't') $timestamp = $v;
            if ($k === 'v') $receivedSignature = $v;
        }
    }

    if (empty($timestamp) || empty($receivedSignature)) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

    return hash_equals($expectedSignature, $receivedSignature);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    $conn->close();
    exit;
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data || !isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    $conn->close();
    exit;
}

$signatureHeader = $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';
if (!empty($signatureHeader) || !empty(getWebhookSecret())) {
    if (!verifyWebhookSignature($payload, $signatureHeader)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid signature']);
        $conn->close();
        exit;
    }
}

$eventType = $data['data']['attributes']['type'] ?? $data['data']['attributes']['event_type'] ?? '';
$eventData = $data['data']['attributes']['data'] ?? $data['data'] ?? [];

if (empty($eventType)) {
    $eventType = $eventData['attributes']['type'] ?? '';
}

if (strpos($eventType, 'checkout_session.payment.paid') !== false || strpos($eventType, 'payment.paid') !== false) {
    $attributes = $eventData['attributes'] ?? [];

    $checkoutSessionId = '';
    $paymentIntentId = '';

    if (isset($data['data']['id']) && strpos($data['data']['id'] ?? '', 'cs_') === 0) {
        $checkoutSessionId = $data['data']['id'];
        $paymentIntentId = $attributes['payment_intent']['id'] ?? $attributes['payments'][0]['id'] ?? '';
    } elseif (isset($data['data']['id']) && strpos($data['data']['id'] ?? '', 'pi_') === 0) {
        $paymentIntentId = $data['data']['id'];
    }

    if (empty($checkoutSessionId) && !empty($paymentIntentId)) {
        $piResult = $conn->query("SELECT id FROM orders WHERE paymongo_payment_id = '$paymentIntentId' LIMIT 1");
        if ($piResult && $piResult->num_rows > 0) {
            $conn->close();
            echo json_encode(['success' => true, 'message' => 'Already processed']);
            exit;
        }
    }

    if (!empty($checkoutSessionId)) {
        $stmt = $conn->prepare("SELECT id, user_id, order_reference, payment_status FROM orders WHERE checkout_session_id = ?");
        $stmt->bind_param('s', $checkoutSessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();

        if ($order && $order['payment_status'] !== 'paid') {
            $paymentId = $paymentIntentId ?: ($attributes['payment_intent']['id'] ?? '');

            $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', paymongo_payment_id = ?, paid_at = NOW(), updated_at = NOW() WHERE id = ? AND payment_status != 'paid'");
            $updateStmt->bind_param('si', $paymentId, $order['id']);
            $updateStmt->execute();
            $updateStmt->close();

            $orderRef = $order['order_reference'] ?? 'INK-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);

            $timelineStmt = $conn->prepare("INSERT INTO order_timeline (order_id, from_status, to_status, changed_by, notes, created_at) VALUES (?, NULL, 'pending', 0, ?, NOW())");
            $notes = "Payment confirmed via PayMongo. Payment ID: $paymentId";
            $timelineStmt->bind_param('is', $order['id'], $notes);
            $timelineStmt->execute();
            $timelineStmt->close();

            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at) VALUES (?, 'payment_received', 'Payment Received', ?, 'order', ?, NOW())");
            $notifBody = "Payment for Order #$orderRef has been confirmed via PayMongo.";
            $notifStmt->bind_param('isi', $order['user_id'], $notifBody, $order['id']);
            $notifStmt->execute();
            $notifStmt->close();
        }
    }
}

http_response_code(200);
echo json_encode(['success' => true]);
$conn->close();
