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

    $token = isset($data['token']) ? trim($data['token']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';
    $confirm_password = isset($data['confirm_password']) ? trim($data['confirm_password']) : '';
    $answer1 = isset($data['answer1']) ? trim($data['answer1']) : '';
    $answer2 = isset($data['answer2']) ? trim($data['answer2']) : '';
    
    if (empty($token) || empty($password) || empty($confirm_password) || empty($answer1) || empty($answer2)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'All fields are required.'];
        header('Location: ../reset-password.php?token=' . urlencode($token));
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Passwords do not match.'];
        header('Location: ../reset-password.php?token=' . urlencode($token));
        exit();
    }

    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password must be at least 8 characters and include one uppercase letter, one number, and one special symbol.'];
        header('Location: ../reset-password.php?token=' . urlencode($token));
        exit();
    }

    $stmt = $conn->prepare("SELECT prt.id, prt.user_id, prt.expires_at, prt.used, u.security_answer_1, u.security_answer_2 FROM password_reset_tokens prt JOIN users u ON prt.user_id = u.id WHERE prt.token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid password reset token.'];
        header('Location: ../forgot-password.php');
        exit();
    }

    $row = $result->fetch_assoc();
    if ($row['used'] || strtotime($row['expires_at']) < time()) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'This password reset token is invalid or expired.'];
        header('Location: ../forgot-password.php');
        exit();
    }

    // Verify security answers
    $answerHash1 = $row['security_answer_1'];
    $answerHash2 = $row['security_answer_2'];
    $validAnswers = password_verify(strtolower($answer1), $answerHash1) && password_verify(strtolower($answer2), $answerHash2);
    
    if (!$validAnswers) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Security answers do not match our records.'];
        header('Location: ../reset-password.php?token=' . urlencode($token));
        exit();
    }

    $stmt->close();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param('si', $passwordHash, $row['user_id']);
    $update->execute();
    $update->close();

    $markUsed = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
    $markUsed->bind_param('i', $row['id']);
    $markUsed->execute();
    $markUsed->close();

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Your password has been updated successfully.'];
    header('Location: ../index.php#login-panel');
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
