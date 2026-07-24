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
require_once __DIR__ . '/../includes/csrf-helper.php';
require_once __DIR__ . '/../includes/turnstile-config.php';
require_once __DIR__ . '/../includes/session-helper.php';
require_once __DIR__ . '/../db-config.php';

secureSessionStart();

function logLoginAttempt(mysqli $conn, string $ip, int $success): void {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempted_at, success) VALUES (?, NOW(), ?)");
    $stmt->bind_param('si', $ip, $success);
    $stmt->execute();
    $stmt->close();
}

$data = json_decode(file_get_contents('php://input'), true);
$credential = $data['credential'] ?? '';
$turnstileToken = $data['turnstile_token'] ?? '';
$csrfToken = $data['csrf_token'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (empty($credential)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing credential']);
    exit();
}

// Verify CSRF token
if (!verifyCsrfToken($csrfToken)) {
    logLoginAttempt($conn, $ip, 0);
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request. Please refresh the page and try again.']);
    exit();
}

// Verify Turnstile token
if (!empty($turnstileToken)) {
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => TURNSTILE_SECRET_KEY,
        'response' => $turnstileToken,
        'remoteip' => $ip
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $verifyResult = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$verifyResult) {
        logLoginAttempt($conn, $ip, 0);
        echo json_encode(['error' => 'Could not verify security check. Please try again.']);
        exit();
    }

    $verifyData = json_decode($verifyResult, true);
    if (!$verifyData || !($verifyData['success'] ?? false)) {
        logLoginAttempt($conn, $ip, 0);
        echo json_encode(['error' => 'Security check failed. Please try again.']);
        exit();
    }
}

// Rate limiting: max 5 failed attempts per IP per 15 minutes
$rateLimitWindow = date('Y-m-d H:i:s', strtotime('-15 minutes'));
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND attempted_at >= ? AND success = 0");
$stmt->bind_param('ss', $ip, $rateLimitWindow);
$stmt->execute();
$rateResult = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($rateResult && (int)$rateResult['cnt'] >= 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many login attempts. Please try again later.']);
    exit();
}

// Verify the ID token with Google's tokeninfo endpoint
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$response = @file_get_contents($verifyUrl);

if ($response === false) {
    http_response_code(401);
    logLoginAttempt($conn, $ip, 0);
    echo json_encode(['error' => 'Token verification failed']);
    exit();
}

$payload = json_decode($response, true);

if (!$payload || !isset($payload['sub'])) {
    http_response_code(401);
    logLoginAttempt($conn, $ip, 0);
    echo json_encode(['error' => 'Invalid token']);
    exit();
}

// Verify audience matches our Client ID
if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    http_response_code(401);
    logLoginAttempt($conn, $ip, 0);
    echo json_encode(['error' => 'Token audience mismatch']);
    exit();
}

// Verify issuer
if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) {
    http_response_code(401);
    logLoginAttempt($conn, $ip, 0);
    echo json_encode(['error' => 'Invalid issuer']);
    exit();
}

$googleId = $payload['sub'];
$email = $payload['email'] ?? '';
$name = $payload['name'] ?? '';
$avatar = $payload['picture'] ?? '';

if (empty($email)) {
    http_response_code(400);
    logLoginAttempt($conn, $ip, 0);
    echo json_encode(['error' => 'Email not provided by Google']);
    exit();
}

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
$_SESSION['user_profile_photo'] = $user['avatar'] ?? '';

// Sync avatar to profile_photo column for consistency across features (column added Jul 2026)
if (!empty($user['avatar']) && db_column_exists($conn, 'users', 'profile_photo')) {
    $stmt = $conn->prepare("UPDATE users SET profile_photo = avatar WHERE id = ? AND profile_photo IS NULL AND avatar IS NOT NULL AND avatar != ''");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
}

// Log login (not for new registrations, already logged above)
if (!$isNew) {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES ({$user['id']}, 'login', 'User logged in via Google', '" . $conn->real_escape_string($ip) . "', '" . $conn->real_escape_string($ua) . "')");
}

// Check if profile is complete (admin skips this requirement)
$needsProfile = ($user['role'] !== 'admin') && (empty($user['name']) || empty($user['contact_number']) || empty($user['address']));

$redirect = $user['role'] === 'admin' ? $basePath . '/admin/dashboard.php' : $basePath . '/index.php';

// Log successful login attempt
logLoginAttempt($conn, $ip, 1);

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
