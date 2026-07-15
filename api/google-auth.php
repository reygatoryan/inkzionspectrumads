<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../includes/google-config.php';
require_once __DIR__ . '/../db-config.php';

$data = json_decode(file_get_contents('php://input'), true);
$credential = $data['credential'] ?? '';

if (empty($credential)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing credential']);
    exit();
}

// Verify the ID token with Google's tokeninfo endpoint
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$response = @file_get_contents($verifyUrl);

if ($response === false) {
    http_response_code(401);
    echo json_encode(['error' => 'Token verification failed']);
    exit();
}

$payload = json_decode($response, true);

if (!$payload || !isset($payload['sub'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit();
}

// Verify audience matches our Client ID
if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    http_response_code(401);
    echo json_encode(['error' => 'Token audience mismatch']);
    exit();
}

// Verify issuer
if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid issuer']);
    exit();
}

$googleId = $payload['sub'];
$email = $payload['email'] ?? '';
$name = $payload['name'] ?? '';
$avatar = $payload['picture'] ?? '';

if (empty($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email not provided by Google']);
    exit();
}

session_start();

// Compute root-relative base path so frontend JS resolves redirects correctly
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

// Check if user exists by google_id
$stmt = $conn->prepare("SELECT id, name, email, password, contact_number, address, role, avatar FROM users WHERE google_id = ?");
$stmt->bind_param("s", $googleId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$isNew = false;

if (!$user) {
    // Check if user exists by email (link existing account)
    $stmt = $conn->prepare("SELECT id, name, email, password, contact_number, address, role, avatar FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Link google_id to existing account; promote to admin if email matches
        $newRole = $email === ADMIN_EMAIL ? 'admin' : $user['role'];
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, avatar = COALESCE(NULLIF(?, ''), avatar), role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $googleId, $avatar, $newRole, $user['id']);
        $stmt->execute();
        $stmt->close();
        $user['role'] = $newRole;
    } else {
        // Create new user (admin role if email matches)
        $role = $email === ADMIN_EMAIL ? 'admin' : 'user';
        $stmt = $conn->prepare("INSERT INTO users (name, email, google_id, avatar, role, contact_number, address) VALUES (?, ?, ?, ?, ?, '', '')");
        $stmt->bind_param("sssss", $name, $email, $googleId, $avatar, $role);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        $user = [
            'id' => $newId,
            'name' => $name,
            'email' => $email,
            'password' => null,
            'contact_number' => '',
            'address' => '',
            'role' => $role,
            'avatar' => $avatar
        ];
        $isNew = true;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES ($newId, 'registration', 'User registered via Google ($email)', '" . $conn->real_escape_string($ip) . "', '" . $conn->real_escape_string($ua) . "')");
    }
}

// Auto-promote if this user's email matches admin email but role hasn't been set yet
if ($email === ADMIN_EMAIL && $user['role'] !== 'admin') {
    $conn->query("UPDATE users SET role = 'admin' WHERE id = {$user['id']}");
    $user['role'] = 'admin';
}

// Set session
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

// Log login (not for new registrations, already logged above)
if (!$isNew) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES ({$user['id']}, 'login', 'User logged in via Google', '" . $conn->real_escape_string($ip) . "', '" . $conn->real_escape_string($ua) . "')");
}

// Check if profile is complete (admin skips this requirement)
$needsProfile = ($user['role'] !== 'admin') && (empty($user['name']) || empty($user['contact_number']) || empty($user['address']));

$redirect = $user['role'] === 'admin' ? $basePath . '/admin/dashboard.php' : $basePath . '/index.php';

echo json_encode([
    'ok' => true,
    'is_new' => $isNew,
    'needs_profile' => $needsProfile,
    'redirect' => $redirect,
    'user' => [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'avatar' => $user['avatar']
    ]
]);

$conn->close();
