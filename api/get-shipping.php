<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../db-config.php';
$result = $conn->query("SELECT settings FROM shipping_settings WHERE section_key='shipping' LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'settings' => json_decode($row['settings'], true)]);
} else {
    echo json_encode(['success' => false, 'error' => 'No settings found']);
}
$conn->close();
