<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "data: {\"error\":\"unauthorized\"}\n\n";
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';
session_write_close();

require_once '../db-config.php';

$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$lastMessageId = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;
$checkTyping = isset($_GET['check_typing']) ? (int)$_GET['check_typing'] : 0;

if (!$conversationId) {
    echo "data: {\"error\":\"no conversation_id\"}\n\n";
    exit;
}

$checkStmt = $conn->prepare("SELECT id FROM chat_conversations WHERE id = ? AND (user_id = ? OR admin_id = ?)");
$checkStmt->bind_param('iii', $conversationId, $userId, $userId);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows === 0) {
    echo "data: {\"error\":\"access denied\"}\n\n";
    exit;
}
$checkStmt->close();

set_time_limit(0);
if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

echo "retry: 2000\n\n";

while (!connection_aborted()) {
    $newMessages = [];

    if ($lastMessageId > 0) {
        $msgStmt = $conn->prepare("
            SELECT cm.*, u.name as sender_name, u.role as sender_role
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.conversation_id = ? AND cm.id > ?
            ORDER BY cm.created_at ASC
        ");
        $msgStmt->bind_param('ii', $conversationId, $lastMessageId);
        $msgStmt->execute();
        $result = $msgStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $newMessages[] = $row;
        }
        $msgStmt->close();
    }

    // Mark received messages as read
    if (count($newMessages) > 0) {
        $updateStmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0");
        $updateStmt->bind_param('ii', $conversationId, $userId);
        $updateStmt->execute();
        $updateStmt->close();

        $lastMessageId = $newMessages[count($newMessages) - 1]['id'];
        echo "id: $lastMessageId\n";
        echo "data: " . json_encode([
            'type' => 'messages',
            'messages' => $newMessages,
            'last_message_id' => $lastMessageId
        ]) . "\n\n";
        flush();
    }

    // Typing status (included periodically)
    if ($checkTyping && count($newMessages) === 0) {
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
        $typingRow = $typingStmt->get_result()->fetch_assoc();
        $typingStmt->close();

        $typingData = $typingRow
            ? ['user_id' => (int)$typingRow['user_id'], 'user_name' => $typingRow['user_name']]
            : null;

        $unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE conversation_id = ? AND sender_id != ? AND is_read = 0");
        $unreadStmt->bind_param('ii', $conversationId, $userId);
        $unreadStmt->execute();
        $unreadCount = (int)$unreadStmt->get_result()->fetch_assoc()['cnt'];
        $unreadStmt->close();

        echo "data: " . json_encode([
            'type' => 'typing',
            'typing' => $typingData,
            'unread_count' => $unreadCount
        ]) . "\n\n";
        flush();
    }

    if (count($newMessages) === 0) {
        echo ": heartbeat\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }

    sleep(1);
}

$conn->close();
