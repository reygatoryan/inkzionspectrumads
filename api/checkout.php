<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db-config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';

// Validate required fields
$contact = $data['contact'] ?? [];
$address = $data['address'] ?? [];
$payment = $data['payment'] ?? [];
$items = $data['items'] ?? [];
$total = isset($data['total']) ? (float)$data['total'] : 0;
$orderNotes = isset($data['order_notes']) ? trim($data['order_notes']) : '';

if (empty($contact['firstName']) || empty($contact['lastName']) || empty($contact['email']) || empty($contact['phone'])) {
    echo json_encode(['success' => false, 'error' => 'Contact information is required']);
    exit;
}

if (empty($address['street']) || empty($address['city']) || empty($address['province']) || empty($address['zipCode'])) {
    echo json_encode(['success' => false, 'error' => 'Delivery address is required']);
    exit;
}

if (empty($items) || $total <= 0) {
    echo json_encode(['success' => false, 'error' => 'No item selected. Please choose a product to purchase.']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Generate unique order reference
    $orderReference = 'ORD-' . strtoupper(uniqid());
    
    // Determine payment method and status
    $paymentMethod = $payment['method'] ?? 'cod';
    $paymentStatus = 'pending';
    $receiptData = null;
    
    switch ($paymentMethod) {
        case 'credit-card':
            $paymentStatus = 'paid'; // Simulate instant payment
            break;
        case 'gcash':
            $paymentStatus = 'pending'; // GCash payment — verified via reference number + receipt
            break;
        case 'downpayment':
            $paymentStatus = 'pending'; // 50% upfront via CC, balance via selected method
            break;
    }
    
    // Calculate total weight from items
    $totalWeight = 0;
    foreach ($items as $item) {
        $itemWeight = isset($item['weight']) ? (float)$item['weight'] : 0;
        $itemQty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $totalWeight += $itemWeight * $itemQty;
    }

    // Create order
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, order_reference, total_amount, status, payment_method, payment_status,
            contact_name, contact_email, contact_phone,
            delivery_address, delivery_city, delivery_province, delivery_zip, delivery_country, delivery_notes,
            order_notes, total_weight, created_at, updated_at
        ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $contactName = $contact['firstName'] . ' ' . $contact['lastName'];
    $stmt->bind_param('isdssssssssssssd',
        $userId,
        $orderReference,
        $total,
        $paymentMethod,
        $paymentStatus,
        $contactName,
        $contact['email'],
        $contact['phone'],
        $address['street'],
        $address['city'],
        $address['province'],
        $address['zipCode'],
        $address['country'] ?? 'Philippines',
        $address['notes'] ?? '',
        $orderNotes,
        $totalWeight
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create order');
    }
    
    $orderId = $conn->insert_id;
    $stmt->close();
    
    // Add order items and reduce inventory
    $itemStmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, product_name)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $inventoryStmt = $conn->prepare("
        UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?
    ");
    
    foreach ($items as $item) {
        $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $price = isset($item['price']) ? (float)$item['price'] : 0;
        $name = isset($item['name']) ? $item['name'] : 'Unknown Product';
        
        // Insert order item
        $itemStmt->bind_param('iiids', $orderId, $productId, $quantity, $price, $name);
        $itemStmt->execute();
        
        // Reduce inventory if product exists
        if ($productId > 0) {
            $inventoryStmt->bind_param('iii', $quantity, $productId, $quantity);
            $inventoryStmt->execute();
            if ($inventoryStmt->affected_rows === 0) {
                throw new Exception('Insufficient stock for "' . $name . '". Only ' . ($productId > 0 ? 'limited' : '0') . ' item(s) available.');
            }
        }
    }
    
    $itemStmt->close();
    $inventoryStmt->close();
    
    // Store GCash reference number if provided
    $gcashRefNo = $payment['gcash_ref_no'] ?? '';
    if (!empty($gcashRefNo)) {
        $updateStmt = $conn->prepare("
            UPDATE orders SET payment_reference = ? WHERE id = ?
        ");
        $updateStmt->bind_param('si', $gcashRefNo, $orderId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Store balance method for downpayment
    $balanceMethod = $payment['balance_method'] ?? '';
    if ($paymentMethod === 'downpayment' && !empty($balanceMethod)) {
        $updateStmt = $conn->prepare("
            UPDATE orders SET balance_method = ? WHERE id = ?
        ");
        $updateStmt->bind_param('si', $balanceMethod, $orderId);
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    // Create order timeline entry
    $timelineStmt = $conn->prepare("
        INSERT INTO order_timeline (order_id, from_status, to_status, changed_by, notes, created_at)
        VALUES (?, NULL, 'pending', ?, 'Order placed by customer', NOW())
    ");
    $timelineStmt->bind_param('ii', $orderId, $userId);
    $timelineStmt->execute();
    $timelineStmt->close();

    // Log order placed in activity logs
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES ($userId, 'order_placed', 'Order #$orderReference placed (₱" . number_format($total, 2) . ")', '" . $conn->real_escape_string($ip) . "', '" . $conn->real_escape_string($ua) . "')");
    
    // Create notification for customer
    $customerNotif = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
        VALUES (?, 'order_placed', ?, ?, 'order', ?, NOW())
    ");
    $customerTitle = "Order #$orderReference Confirmed";
    $customerBody = "Your order #$orderReference has been placed successfully. Total: ₱" . number_format($total, 2);
    $customerNotif->bind_param('issi', $userId, $customerTitle, $customerBody, $orderId);
    $customerNotif->execute();
    $customerNotif->close();
    
    // Get sellers for this order and notify them
    $sellersStmt = $conn->prepare("
        SELECT DISTINCT p.admin_id FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ? AND p.admin_id IS NOT NULL
    ");
    $sellersStmt->bind_param('i', $orderId);
    $sellersStmt->execute();
    $sellersResult = $sellersStmt->get_result();
    
    $sellerNotif = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
        VALUES (?, 'new_order', ?, ?, 'order', ?, NOW())
    ");
    
    while ($seller = $sellersResult->fetch_assoc()) {
        $sellerId = $seller['admin_id'];
        $sellerTitle = "New Order #$orderReference";
        $sellerBody = "You have a new order #$orderReference. Please review and process.";
        $sellerNotif->bind_param('issi', $sellerId, $sellerTitle, $sellerBody, $orderId);
        $sellerNotif->execute();
    }
    $sellersStmt->close();
    $sellerNotif->close();
    
    // Notify admins
    $adminNotif = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
        SELECT id, 'new_order', ?, ?, 'order', ?, NOW()
        FROM users WHERE role = 'admin'
    ");
    $adminTitle = "New Order #$orderReference";
    $adminBody = "A new order #$orderReference has been placed by $contactName.";
    $adminNotif->bind_param('ssi', $adminTitle, $adminBody, $orderId);
    $adminNotif->execute();
    $adminNotif->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $orderId,
        'order_reference' => $orderReference,
        'payment_status' => $paymentStatus
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Checkout error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to place order. Please try again.']);
}

$conn->close();
