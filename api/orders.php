<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db-config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $search = $_GET['search'] ?? '';

    $conditions = ["o.user_id = ?"];
    $params = [$userId];
    $types = 'i';

    if ($search) {
        $conditions[] = "(o.id LIKE ? OR o.order_reference LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $where = implode(' AND ', $conditions);

    $sql = "SELECT o.id, o.order_reference, o.total_amount, o.status, o.payment_method, o.payment_status,
                   o.created_at, o.courier, o.tracking_number, o.estimated_delivery
            FROM orders o
            WHERE $where
            ORDER BY o.created_at DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $itemStmt = $conn->prepare("SELECT oi.quantity, oi.unit_price, COALESCE(p.name, oi.product_name) AS product_name, COALESCE(p.image_url, '') AS image_url
                                     FROM order_items oi
                                     LEFT JOIN products p ON oi.product_id = p.id
                                     WHERE oi.order_id = ?");
        $itemStmt->bind_param('i', $row['id']);
        $itemStmt->execute();
        $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemStmt->close();
        $row['items'] = $items;
        $orders[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
