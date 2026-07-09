<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
require_once __DIR__ . '/../db-config.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['settings'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}
$settings = json_encode($input['settings']);
$stmt = $conn->prepare("UPDATE shipping_settings SET settings = ?, updated_at = NOW() WHERE section_key = 'shipping'");
$stmt->bind_param('s', $settings);
if ($stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO shipping_settings (section_key, settings) VALUES ('shipping', ?)");
        $stmt->bind_param('s', $settings);
        $stmt->execute();
    }
    echo json_encode(['success' => true, 'message' => 'Shipping settings saved']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save']);
}
$stmt->close();
$conn->close();
