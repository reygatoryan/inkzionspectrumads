<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db-config.php';

$userId = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['user_role'] ?? '';

if (!$userId || !in_array($role, ['admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $status = $_GET['status'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    
    $query = "
        SELECT o.id, o.order_reference, o.total_amount, o.payment_method, o.payment_status, 
               o.status as order_status, o.created_at,
               u.name as customer_name, u.email as customer_email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1
    ";
    
    if ($role === 'admin') {
        $sellerId = $userId;
        $query .= " AND EXISTS (
            SELECT 1 FROM order_items oi 
            INNER JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = o.id AND p.admin_id = $sellerId
        )";
    }
    
    if ($status) {
        $status = $conn->real_escape_string($status);
        $query .= " AND o.payment_status = '$status'";
    }
    
    if ($dateFrom) {
        $dateFrom = $conn->real_escape_string($dateFrom);
        $query .= " AND DATE(o.created_at) >= '$dateFrom'";
    }
    
    if ($dateTo) {
        $dateTo = $conn->real_escape_string($dateTo);
        $query .= " AND DATE(o.created_at) <= '$dateTo'";
    }
    
    $query .= " ORDER BY o.created_at DESC LIMIT 100";
    
    $payments = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
    exit;
}

if ($action === 'update_payment_status') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $paymentStatus = trim($_POST['payment_status'] ?? '');
    
    if (!$orderId || !in_array($paymentStatus, ['pending', 'paid', 'failed', 'refunded'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    // Verify order belongs to seller (if seller)
    if ($role === 'admin') {
        $sellerId = $userId;
        $checkStmt = $conn->prepare("
            SELECT o.id FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            INNER JOIN products p ON oi.product_id = p.id
            WHERE o.id = ? AND p.admin_id = ?
            LIMIT 1
        ");
        $checkStmt->bind_param('ii', $orderId, $sellerId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
            exit;
        }
        $checkStmt->close();
    }
    
    $stmt = $conn->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
    $stmt->bind_param('si', $paymentStatus, $orderId);
    
    if ($stmt->execute()) {
        // Create notification
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
            VALUES (?, 'payment_updated', ?, ?, 'order', ?, NOW())
        ");
        $notifTitle = "Payment Status Updated";
        $notifBody = "Payment status for order #$orderId has been updated to " . ucfirst($paymentStatus);
        
        // Get customer ID
        $customerStmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
        $customerStmt->bind_param('i', $orderId);
        $customerStmt->execute();
        $customerId = $customerStmt->get_result()->fetch_assoc()['user_id'];
        $customerStmt->close();
        
        $notifStmt->bind_param('issi', $customerId, $notifTitle, $notifBody, $orderId);
        $notifStmt->execute();
        $notifStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update payment status'
        ]);
    }
    $stmt->close();
    exit;
}

if ($action === 'stats') {
    $totalRevenue = $conn->query("
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM orders
        WHERE payment_status = 'paid'
    ")->fetch_assoc()['total'];
    
    $pendingPayments = $conn->query("
        SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as amount
        FROM orders
        WHERE payment_status = 'pending'
    ")->fetch_assoc();
    
    $completedPayments = $conn->query("
        SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as amount
        FROM orders
        WHERE payment_status = 'paid'
    ")->fetch_assoc();
    
    $failedPayments = $conn->query("
        SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as amount
        FROM orders
        WHERE payment_status = 'failed'
    ")->fetch_assoc();
    
    $refundedPayments = $conn->query("
        SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as amount
        FROM orders
        WHERE payment_status = 'refunded'
    ")->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_revenue' => (float)$totalRevenue,
            'pending_count' => (int)$pendingPayments['cnt'],
            'pending_amount' => (float)$pendingPayments['amount'],
            'completed_count' => (int)$completedPayments['cnt'],
            'completed_amount' => (float)$completedPayments['amount'],
            'failed_count' => (int)$failedPayments['cnt'],
            'failed_amount' => (float)$failedPayments['amount'],
            'refunded_count' => (int)$refundedPayments['cnt'],
            'refunded_amount' => (float)$refundedPayments['amount']
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
