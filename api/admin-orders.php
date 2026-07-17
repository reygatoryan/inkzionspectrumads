<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db-config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sellerId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'counts') {
    $tabs = ['all','unpaid','pending','shipping','completed','returns'];
    $counts = [];
    $baseSql = "FROM orders o INNER JOIN order_items oi ON o.id = oi.order_id LEFT JOIN products p ON oi.product_id = p.id WHERE (p.admin_id = ? OR o.admin_id = ?)";
    // All
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c $baseSql");
    $stmt->bind_param('ii', $sellerId, $sellerId); $stmt->execute(); $r = $stmt->get_result();
    $counts['all'] = $r->fetch_assoc()['c']; $stmt->close();
    // Unpaid
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c $baseSql AND o.payment_status = 'pending'");
    $stmt->bind_param('ii', $sellerId, $sellerId); $stmt->execute(); $r = $stmt->get_result();
    $counts['unpaid'] = $r->fetch_assoc()['c']; $stmt->close();
    // Pending
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c $baseSql AND o.status IN ('pending','confirmed')");
    $stmt->bind_param('ii', $sellerId, $sellerId); $stmt->execute(); $r = $stmt->get_result();
    $counts['pending'] = $r->fetch_assoc()['c']; $stmt->close();
    // Shipping
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c $baseSql AND o.status IN ('shipped','delivered')");
    $stmt->bind_param('ii', $sellerId, $sellerId); $stmt->execute(); $r = $stmt->get_result();
    $counts['shipping'] = $r->fetch_assoc()['c']; $stmt->close();
    // Completed
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c $baseSql AND o.status = 'completed'");
    $stmt->bind_param('ii', $sellerId, $sellerId); $stmt->execute(); $r = $stmt->get_result();
    $counts['completed'] = $r->fetch_assoc()['c']; $stmt->close();
    // Returns
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c $baseSql AND o.status IN ('returned','cancelled')");
    $stmt->bind_param('ii', $sellerId, $sellerId); $stmt->execute(); $r = $stmt->get_result();
    $counts['returns'] = $r->fetch_assoc()['c']; $stmt->close();
    
    echo json_encode(['success' => true, 'counts' => $counts]);
    exit;
}

if ($action === 'list') {
    $tab = $_GET['tab'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build WHERE conditions
    $conditions = ["(p.admin_id = ? OR o.admin_id = ?)"];
    $params = [$sellerId, $sellerId];
    $types = 'ii';

    if ($tab === 'unpaid') {
        $conditions[] = "o.payment_status = 'pending'";
    } elseif ($tab === 'pending') {
        $conditions[] = "o.status IN ('pending','confirmed')";
    } elseif ($tab === 'shipping') {
        $conditions[] = "o.status IN ('shipped','delivered')";
    } elseif ($tab === 'completed') {
        $conditions[] = "o.status = 'completed'";
    } elseif ($tab === 'returns') {
        $conditions[] = "o.status IN ('returned','cancelled')";
    }

    if ($search) {
        $conditions[] = "o.id LIKE ? OR o.order_reference LIKE ?";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $where = implode(' AND ', $conditions);

    // Get total count
    $countSql = "SELECT COUNT(DISTINCT o.id) AS total FROM orders o INNER JOIN order_items oi ON o.id = oi.order_id LEFT JOIN products p ON oi.product_id = p.id WHERE $where";
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Get orders with full details
    $sql = "SELECT DISTINCT o.id, o.order_reference, o.total_amount, o.status, o.payment_method, o.payment_status, 
                   o.created_at, u.name AS customer_name, u.email AS customer_email, u.contact_number AS customer_phone,
                   o.delivery_address, o.delivery_city, o.delivery_province, o.contact_name,
                   o.total_weight, o.shipping_fee, o.courier, o.tracking_number, o.estimated_delivery
            FROM orders o
            INNER JOIN users u ON o.user_id = u.id
            INNER JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE $where
            ORDER BY o.created_at DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Get items for this order
        $itemStmt = $conn->prepare("SELECT oi.quantity, oi.unit_price, COALESCE(p.name, oi.product_name) AS product_name, COALESCE(p.image_url, '') AS image_url 
                                     FROM order_items oi 
                                     LEFT JOIN products p ON oi.product_id = p.id 
                                     WHERE oi.order_id = ?");
        $itemStmt->bind_param('i', $row['id']);
        $itemStmt->execute();
        $itemsResult = $itemStmt->get_result();
        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }
        $itemStmt->close();
        $row['items'] = $items;
        $orders[] = $row;
    }
    $stmt->close();

    $totalPages = ceil($total / $limit);
    echo json_encode(['success' => true, 'orders' => $orders, 'total' => $total, 'page' => $page, 'total_pages' => $totalPages]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
