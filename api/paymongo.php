<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db-config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

function getPayMongoKeys() {
    global $conn;
    $result = $conn->query("SELECT settings FROM shipping_settings WHERE section_key='shipping' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $settings = json_decode($row['settings'], true);
        $secret = $settings['payment']['paymongo']['secret_key'] ?? '';
        $pub = $settings['payment']['paymongo']['public_key'] ?? '';
        $enabled = $settings['payment']['paymongo']['enabled'] ?? false;
        return ['secret_key' => $secret, 'public_key' => $pub, 'enabled' => $enabled];
    }
    return ['secret_key' => '', 'public_key' => '', 'enabled' => false];
}

function paymongoApiCall($endpoint, $data = null, $method = 'POST') {
    $keys = getPayMongoKeys();
    if (empty($keys['secret_key'])) {
        return ['success' => false, 'error' => 'PayMongo not configured'];
    }

    $url = 'https://api.paymongo.com/v1/' . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($keys['secret_key'] . ':')
    ]);

    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'PayMongo connection error: ' . $error];
    }

    $result = json_decode($response, true);

    if ($httpCode >= 400) {
        $errMsg = $result['errors'][0]['detail'] ?? 'Unknown PayMongo error';
        return ['success' => false, 'error' => $errMsg];
    }

    return ['success' => true, 'data' => $result];
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = dirname($_SERVER['SCRIPT_NAME']);
    $dir = str_replace('/api', '', $dir);
    return $protocol . $host . $dir;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_session') {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($input['order_id']) ? intval($input['order_id']) : 0;

    if (!$orderId) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        $conn->close();
        exit;
    }

    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    $userId = (int)$_SESSION['user_id'];

    if ($isAdmin) {
        $stmt = $conn->prepare("SELECT o.*, u.name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $orderId, $userId);
    }
    if ($isAdmin) {
        $stmt->bind_param('i', $orderId);
    }
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        $conn->close();
        exit;
    }

    if ($order['payment_status'] === 'paid') {
        echo json_encode(['success' => false, 'error' => 'Order is already paid']);
        $conn->close();
        exit;
    }

    if ($order['payment_method'] !== 'gcash' && $order['payment_method'] !== 'credit_card') {
        echo json_encode(['success' => false, 'error' => 'Payment method not supported for PayMongo']);
        $conn->close();
        exit;
    }

    $keys = getPayMongoKeys();
    if (!$keys['enabled'] || empty($keys['secret_key'])) {
        echo json_encode(['success' => false, 'error' => 'PayMongo payment is not enabled']);
        $conn->close();
        exit;
    }

    if (!empty($order['checkout_session_id']) && !empty($order['checkout_url'])) {
        echo json_encode(['success' => true, 'checkout_url' => $order['checkout_url'], 'session_id' => $order['checkout_session_id']]);
        $conn->close();
        exit;
    }

    $baseUrl = getBaseUrl();
    $orderRef = $order['order_reference'] ?? 'INK-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
    $totalCents = intval(round(floatval($order['total_amount']) * 100));

    if ($totalCents <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order amount']);
        $conn->close();
        exit;
    }

    $paymentMethodTypes = ['gcash', 'card'];

    $payload = [
        'data' => [
            'attributes' => [
                'billing' => [
                    'name' => $order['contact_name'] ?? ($isAdmin ? ($order['name'] ?? 'Customer') : ($_SESSION['user_name'] ?? 'Customer')),
                    'email' => $order['contact_email'] ?? ($isAdmin ? ($order['email'] ?? '') : ($_SESSION['user_email'] ?? '')),
                    'phone' => $order['contact_phone'] ?? '',
                ],
                'line_items' => [
                    [
                        'amount' => $totalCents,
                        'currency' => 'PHP',
                        'name' => 'Order #' . $orderRef,
                        'quantity' => 1,
                    ]
                ],
                'payment_method_types' => $paymentMethodTypes,
                'success_url' => $baseUrl . '/customer/order-tracking.php?id=' . $orderId . '&payment=success',
                'cancel_url' => $baseUrl . '/customer/order-tracking.php?id=' . $orderId . '&payment=cancelled',
            ]
        ]
    ];

    $result = paymongoApiCall('checkout_sessions', $payload);

    if (!$result['success']) {
        echo json_encode(['success' => false, 'error' => $result['error']]);
        $conn->close();
        exit;
    }

    $sessionData = $result['data']['data'] ?? [];
    $sessionId = $sessionData['id'] ?? '';
    $checkoutUrl = $sessionData['attributes']['checkout_url'] ?? '';

    if (empty($sessionId) || empty($checkoutUrl)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create checkout session']);
        $conn->close();
        exit;
    }

    $updateStmt = $conn->prepare("UPDATE orders SET checkout_session_id = ?, checkout_url = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('ssi', $sessionId, $checkoutUrl, $orderId);
    $updateStmt->execute();
    $updateStmt->close();

    echo json_encode(['success' => true, 'checkout_url' => $checkoutUrl, 'session_id' => $sessionId]);
    $conn->close();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') && $action === 'check_status') {
    $orderId = intval($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

    if (!$orderId) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        $conn->close();
        exit;
    }

    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    $userId = (int)$_SESSION['user_id'];

    if ($isAdmin) {
        $stmt = $conn->prepare("SELECT id, user_id, checkout_session_id, payment_status FROM orders WHERE id = ?");
        $stmt->bind_param('i', $orderId);
    } else {
        $stmt = $conn->prepare("SELECT id, user_id, checkout_session_id, payment_status FROM orders WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $orderId, $userId);
    }
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        $conn->close();
        exit;
    }

    if ($order['payment_status'] === 'paid') {
        echo json_encode(['success' => true, 'payment_status' => 'paid']);
        $conn->close();
        exit;
    }

    if (empty($order['checkout_session_id'])) {
        echo json_encode(['success' => false, 'error' => 'No checkout session found']);
        $conn->close();
        exit;
    }

    $result = paymongoApiCall('checkout_sessions/' . $order['checkout_session_id'], null, 'GET');

    if (!$result['success']) {
        echo json_encode(['success' => false, 'error' => $result['error']]);
        $conn->close();
        exit;
    }

    $attributes = $result['data']['data']['attributes'] ?? [];
    $paymentIntentId = $attributes['payment_intent']['id'] ?? '';
    $paymentIntentStatus = $attributes['payment_intent']['attributes']['status'] ?? '';

    if ($paymentIntentStatus === 'succeeded' || $paymentIntentStatus === 'paid') {
        $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', paymongo_payment_id = ?, paid_at = NOW() WHERE id = ? AND payment_status != 'paid'");
        $updateStmt->bind_param('si', $paymentIntentId, $orderId);
        $updateStmt->execute();
        $updateStmt->close();

        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at) VALUES (?, 'payment_received', 'Payment Received', 'Payment for Order #? has been confirmed.', 'order', ?, NOW())");
        $customerId = $order['user_id'] ?? 0;
        if ($customerId) {
            $notifStmt->bind_param('iii', $customerId, $orderId, $orderId);
            $notifStmt->execute();
        }
        $notifStmt->close();

        echo json_encode(['success' => true, 'payment_status' => 'paid', 'payment_intent_id' => $paymentIntentId]);
    } else {
        echo json_encode(['success' => true, 'payment_status' => $order['payment_status'], 'checkout_status' => $paymentIntentStatus]);
    }

    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_paid') {
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin only']);
        $conn->close();
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($input['order_id']) ? intval($input['order_id']) : 0;

    if (!$orderId) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("SELECT id, payment_status, user_id, order_reference FROM orders WHERE id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        $conn->close();
        exit;
    }

    if ($order['payment_status'] === 'paid') {
        echo json_encode(['success' => false, 'error' => 'Order is already paid']);
        $conn->close();
        exit;
    }

    $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('i', $orderId);
    $updateStmt->execute();
    $updateStmt->close();

    $orderRef = $order['order_reference'] ?? 'INK-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);

    $timelineStmt = $conn->prepare("INSERT INTO order_timeline (order_id, from_status, to_status, changed_by, notes, created_at) VALUES (?, NULL, 'pending', ?, 'Payment manually marked as paid by admin', NOW())");
    $adminId = (int)$_SESSION['user_id'];
    $timelineStmt->bind_param('ii', $orderId, $adminId);
    $timelineStmt->execute();
    $timelineStmt->close();

    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at) VALUES (?, 'payment_received', 'Payment Confirmed', ?, 'order', ?, NOW())");
    $notifBody = "Payment for Order #$orderRef has been confirmed by admin.";
    $notifStmt->bind_param('isi', $order['user_id'], $notifBody, $orderId);
    $notifStmt->execute();
    $notifStmt->close();

    echo json_encode(['success' => true, 'message' => "Order #$orderRef marked as paid"]);
    $conn->close();
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
$conn->close();
