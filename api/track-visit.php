<?php
session_start();
require_once __DIR__ . '/../db-config.php';

$pageUrl = isset($_GET['url']) ? trim($_GET['url']) : ($_SERVER['HTTP_REFERER'] ?? '/');
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$stmt = $conn->prepare("INSERT INTO page_views (page_url, visitor_ip, user_agent, user_id, viewed_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param('sssi', $pageUrl, $ip, $ua, $userId);
$stmt->execute();
$stmt->close();
$conn->close();

http_response_code(204);
