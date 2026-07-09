<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';

$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$lastMessageId = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;
$checkTyping = isset($_GET['check_typing']) ? (int)$_GET['check_typing'] : 0;

if (!$conversationId) {
    // Poll for conversation list updates only
    if ($userRole === 'admin') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.id AND is_read = 0 AND sender_id != ?) > 0 THEN 1 ELSE 0 END) as unread_count
            FROM chat_conversations cc
            WHERE cc.admin_id = ?
        ");
        $stmt->bind_param('ii', $userId, $userId);
    } else {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.id AND is_read = 0 AND sender_id != ?) > 0 THEN 1 ELSE 0 END) as unread_count
            FROM chat_conversations cc
            WHERE cc.user_id = ?
        ");
        $stmt->bind_param('ii', $userId, $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'type' => 'conversations_summary',
        'total' => (int)($result['total'] ?? 0),
        'unread_total' => (int)($result['unread_count'] ?? 0)
    ]);
    $conn->close();
    exit;
}

// Verify access to conversation
$checkStmt = $conn->prepare("
    SELECT id FROM chat_conversations 
    WHERE id = ? AND (user_id = ? OR admin_id = ?)
");
$checkStmt->bind_param('iii', $conversationId, $userId, $userId);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Conversation not found']);
    $checkStmt->close();
    $conn->close();
    exit;
}
$checkStmt->close();

// Mark messages as read (only if user is not the sender)
$updateStmt = $conn->prepare("
    UPDATE chat_messages 
    SET is_read = 1 
    WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
");
$updateStmt->bind_param('ii', $conversationId, $userId);
$updateStmt->execute();
$updateStmt->close();

// Get new messages since last_message_id
$newMessages = [];
$hasMore = false;
if ($lastMessageId > 0) {
    // Check if there are more older messages (beyond what's loaded)
    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE conversation_id = ? AND id < ?");
    $countStmt->bind_param('ii', $conversationId, $lastMessageId);
    $countStmt->execute();
    $cntResult = $countStmt->get_result()->fetch_assoc();
    $hasMore = (int)($cntResult['cnt'] ?? 0) > 0;
    $countStmt->close();

    // Fetch only new messages since the last known ID
    $msgStmt = $conn->prepare("
        SELECT cm.*, u.name as sender_name, u.role as sender_role
        FROM chat_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.conversation_id = ? AND cm.id > ?
        ORDER BY cm.created_at ASC
    ");
    $msgStmt->bind_param('ii', $conversationId, $lastMessageId);
} else {
    // First load: get the most recent 50
    $msgStmt = $conn->prepare("
        SELECT cm.*, u.name as sender_name, u.role as sender_role
        FROM chat_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.conversation_id = ?
        ORDER BY cm.created_at DESC
        LIMIT 50
    ");
    $msgStmt->bind_param('i', $conversationId);
}
$msgStmt->execute();
$result = $msgStmt->get_result();
while ($row = $result->fetch_assoc()) {
    $newMessages[] = $row;
}
$msgStmt->close();

// Reverse to chronological for initial load
if ($lastMessageId === 0) {
    $newMessages = array_reverse($newMessages);
    // Check has_more for initial load
    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE conversation_id = ?");
    $countStmt->bind_param('i', $conversationId);
    $countStmt->execute();
    $cntResult = $countStmt->get_result()->fetch_assoc();
    $totalMsgs = (int)($cntResult['cnt'] ?? 0);
    $hasMore = $totalMsgs > 50;
    $countStmt->close();
}

// Get typing status
$typingData = null;
if ($checkTyping) {
    $typingStmt = $conn->prepare("
        SELECT ct.user_id, u.name as user_name
        FROM chat_typing ct
        JOIN users u ON ct.user_id = u.id
        WHERE ct.conversation_id = ? AND ct.is_typing = 1 AND ct.user_id != ?
        AND ct.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ORDER BY ct.updated_at DESC LIMIT 1
    ");
    $typingStmt->bind_param('ii', $conversationId, $userId);
    $typingStmt->execute();
    $typingResult = $typingStmt->get_result();
    $typingRow = $typingResult->fetch_assoc();
    $typingStmt->close();
    if ($typingRow) {
        $typingData = ['user_id' => (int)$typingRow['user_id'], 'user_name' => $typingRow['user_name']];
    }
}

// Get unread count for this conversation
$unreadStmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM chat_messages 
    WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
");
$unreadStmt->bind_param('ii', $conversationId, $userId);
$unreadStmt->execute();
$unreadResult = $unreadStmt->get_result()->fetch_assoc();
$unreadCount = (int)($unreadResult['cnt'] ?? 0);
$unreadStmt->close();

echo json_encode([
    'success' => true,
    'type' => 'messages_poll',
    'messages' => $newMessages,
    'has_more' => $hasMore,
    'typing' => $typingData,
    'unread_count' => $unreadCount,
    'last_message_id' => count($newMessages) > 0 ? $newMessages[count($newMessages) - 1]['id'] : $lastMessageId
]);

$conn->close();
