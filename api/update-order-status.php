<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
if (empty($input)) {
    $input = $_POST;
}
if (empty($input)) {
    $input = $_GET;
}
if (empty($input['status']) && !empty($input['action']) && $input['action'] === 'cancel') {
    $input['status'] = 'cancelled';
}
if (empty($input['order_id']) || empty($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: order_id and status']);
    exit;
}

$orderId = intval($input['order_id']);
$newStatus = $input['status'];
$userId = $_SESSION['user_id'];

$userRoleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$userRoleStmt->bind_param('i', $userId);
$userRoleStmt->execute();
$userRole = $userRoleStmt->get_result()->fetch_assoc();
$userRoleStmt->close();

$role = $userRole['role'] ?? 'user';

$allowedStatuses = [];
if ($role === 'admin') {
    $allowedStatuses = ['confirmed', 'shipped', 'delivered', 'completed', 'cancelled', 'returned'];
} else {
    $allowedStatuses = ['cancelled', 'returned'];
}

if (!in_array($newStatus, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status for your role']);
    exit;
}

$checkStmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
$checkStmt->bind_param('ii', $orderId, $userId);
$checkStmt->execute();
$result = $checkStmt->get_result();
$order = $result->fetch_assoc();
$checkStmt->close();

if (!$order) {
    if ($role === 'admin') {
        $checkStmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ?");
        $checkStmt->bind_param('i', $orderId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $order = $result->fetch_assoc();
        $checkStmt->close();
    }
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
}

$currentStatus = $order['status'];
$validTransitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['shipped', 'cancelled'],
    'shipped' => ['delivered', 'cancelled'],
    'delivered' => ['completed', 'returned'],
    'completed' => ['returned'],
    'cancelled' => [],
    'returned' => [],
];

// Admin can do most transitions; customers only cancel/return
if ($role === 'admin') {
    $adminTransitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => ['completed', 'returned'],
        'completed' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];
    $validTransitions = $adminTransitions;
} else {
    // Customer can only cancel pending/confirmed orders or request return on delivered/completed
    $customerTransitions = [
        'pending' => ['cancelled'],
        'confirmed' => ['cancelled'],
        'delivered' => ['returned'],
        'completed' => ['returned'],
    ];
    $validTransitions = $customerTransitions;
}

if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
    $statusNames = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
    ];
    $currentLabel = $statusNames[$currentStatus] ?? $currentStatus;
    $newLabel = $statusNames[$newStatus] ?? $newStatus;
    $msg = "Cannot change status from \"$currentLabel\" to \"$newLabel\".";
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$updateSql = "UPDATE orders SET status = ?, updated_at = NOW()";
$updateParams = [$newStatus];
$updateTypes = 's';
if ($newStatus === 'completed') {
    $updateSql .= ", payment_status = 'paid'";
}
$updateSql .= " WHERE id = ?";
$updateParams[] = $orderId;
$updateTypes .= 'i';

$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param($updateTypes, ...$updateParams);

    if ($updateStmt->execute()) {
    $timelineStmt = $conn->prepare("
        INSERT INTO order_timeline (order_id, from_status, to_status, changed_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $timelineStmt->bind_param('issi', $orderId, $currentStatus, $newStatus, $userId);
    $timelineStmt->execute();
    $timelineStmt->close();

    // Log order status change in activity logs
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $actionType = 'order_' . $newStatus;
    $statusLabel = ucfirst($newStatus);
    $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES ($userId, '$actionType', 'Order #$orderId status changed to $statusLabel', '" . $conn->real_escape_string($ip) . "', '" . $conn->real_escape_string($ua) . "')");

    $orderDetailsStmt = $conn->prepare("
        SELECT o.user_id as customer_id FROM orders o WHERE o.id = ?
    ");
    $orderDetailsStmt->bind_param('i', $orderId);
    $orderDetailsStmt->execute();
    $orderDetails = $orderDetailsStmt->get_result()->fetch_assoc();
    $orderDetailsStmt->close();

    $customerId = $orderDetails['customer_id'] ?? null;

    $statusLabels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
    ];
    $notifLabel = $statusLabels[$newStatus] ?? ucfirst($newStatus);

    $notifStmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
        VALUES (?, 'order_updated', ?, ?, 'order', ?, NOW())
    ");

    if ($customerId && $role !== 'customer') {
        $notifTitle = "Order #$orderId $notifLabel";
        $notifBody = "Your order #$orderId has been updated to \"$notifLabel\" status.";
        $notifStmt->bind_param('issi', $customerId, $notifTitle, $notifBody, $orderId);
        $notifStmt->execute();
    }

    if ($role === 'admin') {
        $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND id != ? LIMIT 1");
        $adminStmt->bind_param('i', $userId);
        $adminStmt->execute();
        $otherAdmin = $adminStmt->get_result()->fetch_assoc();
        $adminStmt->close();

        if ($otherAdmin) {
            $notifTitle = "Order #$orderId Updated";
            $notifBody = "Order #$orderId has been updated to \"$notifLabel\" status.";
            $notifStmt->bind_param('issi', $otherAdmin['id'], $notifTitle, $notifBody, $orderId);
            $notifStmt->execute();
        }
    }

    $notifStmt->close();

    echo json_encode([
        'success' => true,
        'message' => "Order status updated to $notifLabel successfully",
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update order status. Please try again.']);
}

$updateStmt->close();
$conn->close();
