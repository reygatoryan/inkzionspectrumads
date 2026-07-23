<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/turnstile-config.php';
require_once __DIR__ . '/../db-config.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body']);
    exit;
}

$userId = (int)($_SESSION['user_id']);
$name = trim($data['name'] ?? '');
$contactNumber = trim($data['contact_number'] ?? '');
$address = trim($data['address'] ?? '');
$turnstileToken = $data['turnstile_token'] ?? '';

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Full name is required']);
    exit;
}
if ($contactNumber === '') {
    echo json_encode(['success' => false, 'error' => 'Contact number is required']);
    exit;
}
if ($address === '') {
    echo json_encode(['success' => false, 'error' => 'Delivery address is required']);
    exit;
}

// Verify Turnstile token
$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'secret' => TURNSTILE_SECRET_KEY,
    'response' => $turnstileToken,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$verifyResult = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$verifyResult) {
    echo json_encode(['success' => false, 'error' => 'Could not verify security check. Please try again.']);
    exit;
}

$verifyData = json_decode($verifyResult, true);
if (!$verifyData || !($verifyData['success'] ?? false)) {
    echo json_encode(['success' => false, 'error' => 'Security check failed. Please try again.']);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET name = ?, contact_number = ?, address = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param('sssi', $name, $contactNumber, $address, $userId);

if ($stmt->execute()) {
    $_SESSION['user_name'] = $name;
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

$stmt->close();
$conn->close();
