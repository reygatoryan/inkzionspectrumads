<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db-config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $isJson = true;
    } else {
        $data = $_POST;
        $isJson = false;
    }
    
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';
    
    if (empty($email) || empty($password)) {
        if ($isJson) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password required']);
            exit();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Email and password are required.'];
        header('Location: ../index.php#login-panel');
        exit();
    }
    
    // Query user
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        if ($isJson) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            exit();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email or password.'];
        header('Location: ../index.php');
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Check if this is a Google-only account
    if (!empty($user['google_id']) && empty($user['password'])) {
        if ($isJson) {
            http_response_code(401);
            echo json_encode(['error' => 'This account uses Google Sign-In. Please sign in with Google.']);
            exit();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'This account uses Google Sign-In. Please sign in with Google.'];
        header('Location: ../index.php');
        exit();
    }
    
    if (!password_verify($password, $user['password'])) {
        if ($isJson) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            exit();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email or password.'];
        header('Location: ../index.php#login-panel');
        exit();
    }
    
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    
    // Determine redirect URL based on role
    if ($user['role'] === 'admin') {
        $redirect = '../admin/dashboard.php';
    } else {
        $redirect = '../index.php';
    }
    
    if ($isJson) {
        echo json_encode([
            'ok' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'redirect' => $redirect
        ]);
    } else {
        header('Location: ' . $redirect);
        exit();
    }
    
    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

