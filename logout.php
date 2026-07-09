<?php
session_start();
require_once __DIR__ . '/db-config.php';
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid) {
    $conn->query("UPDATE users SET is_online = 0 WHERE id = $uid");
}
session_unset();
session_destroy();
header('Location: index.php');
exit();
