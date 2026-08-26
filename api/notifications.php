<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
require_once '../db-config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 50) : 20;
    $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

    $stmt = $conn->prepare("
        SELECT id, type, title, body, related_type, related_id, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND is_deleted = 0
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['related_id'] = $row['related_id'] !== null ? (int)$row['related_id'] : null;
        $row['is_read'] = (int)$row['is_read'];
        $notifications[] = $row;
    }
    $stmt->close();

    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $unreadCount = (int)$countStmt->get_result()->fetch_assoc()['cnt'];
    $countStmt->close();

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'total' => count($notifications)
    ]);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        $conn->close();
        exit;
    }

    $action = $data['action'] ?? '';

    if ($action === 'mark_read' && isset($data['id'])) {
        $id = (int)$data['id'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        $conn->close();
        exit;
    }

    if ($action === 'mark_read_all') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        $conn->close();
        exit;
    }

    if ($action === 'mark_read_by_type' && isset($data['related_type'])) {
        $relatedType = $data['related_type'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND related_type = ? AND is_read = 0");
        $stmt->bind_param('is', $userId, $relatedType);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        $conn->close();
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
$conn->close();
