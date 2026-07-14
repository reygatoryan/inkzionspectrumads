<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['order_id']) || empty($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: order_id and status']);
    exit;
}

$orderId = intval($input['order_id']);
$newStatus = $input['status'];
$userId = $_SESSION['user_id'];
$notes = isset($input['notes']) ? trim($input['notes']) : '';
$courier = isset($input['courier']) ? trim($input['courier']) : '';
$trackingNumber = isset($input['tracking_number']) ? trim($input['tracking_number']) : '';
$estimatedDelivery = isset($input['estimated_delivery']) ? trim($input['estimated_delivery']) : '';
$shippingFee = isset($input['shipping_fee']) ? floatval($input['shipping_fee']) : 0;

$allowedStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'completed', 'cancelled', 'returned'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

// Verify order exists
$checkStmt = $conn->prepare("SELECT id, status, user_id FROM orders WHERE id = ?");
$checkStmt->bind_param('i', $orderId);
$checkStmt->execute();
$result = $checkStmt->get_result();
$order = $result->fetch_assoc();
$checkStmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$oldStatus = $order['status'];

// If moving to confirmed, require courier info
if ($newStatus === 'confirmed' && empty($courier)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Courier is required when confirming an order']);
    exit;
}

// Build update query with optional courier fields
$updateSql = "UPDATE orders SET status = ?, updated_at = NOW()";
$params = [$newStatus];
$types = 's';

if ($courier) {
    $updateSql .= ", courier = ?";
    $params[] = $courier;
    $types .= 's';
}
if ($trackingNumber) {
    $updateSql .= ", tracking_number = ?";
    $params[] = $trackingNumber;
    $types .= 's';
}
if ($estimatedDelivery) {
    $updateSql .= ", estimated_delivery = ?";
    $params[] = $estimatedDelivery;
    $types .= 's';
}
if ($shippingFee > 0) {
    $updateSql .= ", shipping_fee = ?";
    $params[] = $shippingFee;
    $types .= 'd';
}
if ($newStatus === 'completed') {
    $updateSql .= ", payment_status = 'paid'";
}

$updateSql .= " WHERE id = ?";
$params[] = $orderId;
$types .= 'i';

$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param($types, ...$params);

if ($updateStmt->execute()) {
    // Record in order timeline
    $timelineNotes = $notes;
    if ($courier) {
        $timelineNotes .= ($timelineNotes ? ' | ' : '') . "Courier: $courier" . ($trackingNumber ? " (Tracking: $trackingNumber)" : '');
    }
    $timelineStmt = $conn->prepare("
        INSERT INTO order_timeline (order_id, from_status, to_status, changed_by, notes, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $timelineStmt->bind_param('issis', $orderId, $oldStatus, $newStatus, $userId, $timelineNotes);
    $timelineStmt->execute();
    $timelineStmt->close();

    // Create notification for the customer
    $statusLabels = [
        'pending' => 'Pending', 'confirmed' => 'Ready to Ship',
        'shipped' => 'Out for Delivery', 'delivered' => 'Delivered',
        'completed' => 'Completed', 'cancelled' => 'Cancelled', 'returned' => 'Returned',
    ];
    $notifLabel = $statusLabels[$newStatus] ?? ucfirst($newStatus);

    // Build richer notification based on status
    if ($newStatus === 'pending') {
        $notifTitle = "Order #$orderId Approved!";
        $notifBody = "Your order #$orderId has been approved and is now being prepared.";
    } elseif ($newStatus === 'confirmed') {
        $notifTitle = "Order #$orderId is Being Prepared";
        $notifBody = "Your order #$orderId is being prepared for shipping.";
        if ($courier) $notifBody .= " Courier: $courier";
        if ($trackingNumber) $notifBody .= " Tracking: $trackingNumber";
    } elseif ($newStatus === 'shipped') {
        $notifTitle = "Order #$orderId Shipped!";
        $notifBody = "Your order #$orderId is on the way!";
        if ($courier) $notifBody .= " Courier: $courier";
        if ($trackingNumber) $notifBody .= " Tracking: $trackingNumber";
        if ($notes) $notifBody .= " Notes: $notes";
    } else {
        $notifTitle = "Order #$orderId $notifLabel";
        $notifBody = "Your order #$orderId has been updated to \"$notifLabel\" status.";
        if ($notes) $notifBody .= " Notes: $notes";
        if ($courier) $notifBody .= " Courier: $courier";
        if ($trackingNumber) $notifBody .= " Tracking: $trackingNumber";
    }

    $notifType = ($newStatus === 'shipped') ? 'order_shipped' : (($newStatus === 'confirmed') ? 'order_preparing' : 'order_updated');

    $customerId = $order['user_id'];
    $notifStmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
        VALUES (?, ?, ?, ?, 'order', ?, NOW())
    ");
    $notifStmt->bind_param('issii', $customerId, $notifType, $notifTitle, $notifBody, $orderId);
    $notifStmt->execute();
    $notifStmt->close();

    echo json_encode(['success' => true, 'message' => "Order status updated to $notifLabel successfully"]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update order status. Please try again.']);
}

$updateStmt->close();
$conn->close();
