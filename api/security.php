<?php
/**
 * Security Helper Functions
 * Provides CSRF protection, input validation, rate limiting, and security utilities
 */

session_start();

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input
 */
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $data);
    }
    
    $data = trim($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Philippine mobile number
 */
function isValidPhilippineMobile($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    return preg_match('/^(\+63|0)?9\d{9}$/', $phone);
}

/**
 * Validate ZIP code (Philippines)
 */
function isValidZipCode($zip) {
    return preg_match('/^\d{4}$/', $zip);
}

/**
 * Rate limiting
 */
function checkRateLimit($action, $maxAttempts = 10, $timeWindow = 60) {
    $key = "rate_limit_{$action}";
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => $now];
    }
    
    $attempt = $_SESSION[$key];
    
    // Reset if time window has passed
    if ($now - $attempt['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => $now];
        return true;
    }
    
    // Check if limit exceeded
    if ($attempt['count'] >= $maxAttempts) {
        return false;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Get rate limit remaining time
 */
function getRateLimitRemaining($action, $timeWindow = 60) {
    $key = "rate_limit_{$action}";
    if (!isset($_SESSION[$key])) {
        return 0;
    }
    $elapsed = time() - $_SESSION[$key]['first_attempt'];
    return max(0, $timeWindow - $elapsed);
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5 * 1024 * 1024) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload failed'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size exceeds limit'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    return ['valid' => true, 'mime_type' => $mimeType];
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return bin2hex(random_bytes(16)) . '.' . $extension;
}

/**
 * Verify password strength
 */
function isStrongPassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if user has permission
 */
function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'];
    
    if ($requiredRole === 'admin') {
        return $userRole === 'admin';
    }
    
    if ($requiredRole === 'seller') {
        return in_array($userRole, ['admin', 'seller']);
    }
    
    if ($requiredRole === 'user') {
        return in_array($userRole, ['admin', 'seller', 'user']);
    }
    
    return false;
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
}

/**
 * Require role
 */
function requireRole($role) {
    requireAuth();
    
    if (!hasPermission($role)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }
}

/**
 * Log security event
 */
function logSecurityEvent($action, $description, $severity = 'info') {
    global $conn;
    
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, metadata, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $metadata = json_encode([
        'severity' => $severity,
        'timestamp' => time(),
        'url' => $_SERVER['REQUEST_URI'] ?? ''
    ]);
    
    $stmt->bind_param('isssss', $userId, $action, $description, $ipAddress, $userAgent, $metadata);
    $stmt->execute();
    $stmt->close();
}

/**
 * Prevent XSS
 */
function escapeOutput($data) {
    if (is_array($data)) {
        return array_map('escapeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and sanitize array inputs
 */
function sanitizeArray($array, $fields) {
    $sanitized = [];
    foreach ($fields as $field => $type) {
        if (isset($array[$field])) {
            $sanitized[$field] = sanitizeInput($array[$field], $type);
        }
    }
    return $sanitized;
}

/**
 * Check for SQL injection patterns (additional layer of security)
 */
function hasSQLInjection($input) {
    $patterns = [
        '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
        '/(--|\#|\/\*|\*\/)/',
        '/(\bOR\b|\bAND\b)\s+\d+\s*=\s*\d+/i',
        '/(\bOR\b|\bAND\b)\s+[\'"][^\'"]*[\'"]\s*=/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Validate JSON input
 */
function getJSONInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'error' => 'Invalid JSON'];
    }
    
    return ['valid' => true, 'data' => $data];
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; img-src \'self\' data: https:; font-src \'self\' https://cdnjs.cloudflare.com; connect-src \'self\'; frame-ancestors \'self\';');
}