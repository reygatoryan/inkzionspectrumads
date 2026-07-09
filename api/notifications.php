<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: ' . (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST, GET, OPTIONS' : 'GET, OPTIONS'));
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

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $query = "
        SELECT id, type, title, body, related_type, related_id, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND is_deleted = 0
    ";
    
    if ($type === 'unread') {
        $query .= " AND is_read = 0";
    } elseif ($type !== 'all') {
        $query .= " AND type = ?";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    
    if ($type === 'unread') {
        $stmt->bind_param('iii', $userId, $limit, $offset);
    } elseif ($type !== 'all') {
        $stmt->bind_param('isii', $userId, $type, $limit, $offset);
    } else {
        $stmt->bind_param('iii', $userId, $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    
    // Get unread count
    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $unreadCount = $countResult->fetch_assoc()['count'];
    $countStmt->close();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unreadCount
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    
    $action = $data['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notifId = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;
        if ($notifId) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $notifId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        $conn->close();
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        $conn->close();
        exit;
    }
    
    if ($action === 'create') {
        $type = $data['type'] ?? 'system';
        $title = trim($data['title'] ?? '');
        $body = trim($data['body'] ?? '');
        $relatedType = $data['related_type'] ?? null;
        $relatedId = isset($data['related_id']) ? (int)$data['related_id'] : null;
        $targetUserId = isset($data['user_id']) ? (int)$data['user_id'] : $userId;
        
        if (empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Title and body are required']);
            $conn->close();
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, body, related_type, related_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('issssi', $targetUserId, $type, $title, $body, $relatedType, $relatedId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification created',
                'notification_id' => $conn->insert_id
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create notification']);
        }
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($action === 'delete') {
        $notifId = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;
        if ($notifId) {
            $stmt = $conn->prepare("UPDATE notifications SET is_deleted = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $notifId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        $conn->close();
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
$conn->close();
