<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['order' => 0, 'custom_request' => 0, 'order_proposal' => 0, 'chat' => 0]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
require_once '../db-config.php';

$counts = [];

$stmt = $conn->prepare("SELECT related_type, COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND is_deleted = 0 GROUP BY related_type");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $counts[$row['related_type']] = (int)$row['count'];
}
$stmt->close();

$chatStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages cm JOIN chat_conversations cc ON cm.conversation_id = cc.id WHERE cc.user_id = ? AND cm.sender_id != ? AND cm.is_read = 0");
$chatStmt->bind_param("ii", $user_id, $user_id);
$chatStmt->execute();
$chatResult = $chatStmt->get_result();
$counts['chat'] = (int)$chatResult->fetch_assoc()['cnt'];
$chatStmt->close();

$defaults = ['order' => 0, 'custom_request' => 0, 'order_proposal' => 0, 'chat' => 0];
$counts = array_merge($defaults, $counts);

echo json_encode($counts);
