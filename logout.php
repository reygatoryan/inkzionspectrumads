<?php
session_start();
require_once __DIR__ . '/db-config.php';
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid) {
    $conn->query("UPDATE users SET is_online = 0 WHERE id = $uid");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES ($uid, 'logout', 'User logged out', '" . $conn->real_escape_string($ip) . "', '" . $conn->real_escape_string($ua) . "')");
}
session_unset();
session_destroy();
header('Location: index.php');
exit();
