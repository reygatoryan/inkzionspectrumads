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

    $step = isset($data['step']) ? (int)$data['step'] : 1;

    if ($step === 1) {
        // Step 1: Look up user by email and return security questions
        $email = isset($data['email']) ? trim($data['email']) : '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Valid email is required']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id, security_question_1, security_question_2 FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Store user_id in session for step 2 verification
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_step'] = 1;
            echo json_encode([
                'success' => true,
                'user_id' => $user['id'],
                'security_question_1' => $user['security_question_1'],
                'security_question_2' => $user['security_question_2']
            ]);
        } else {
            echo json_encode(['error' => 'No account found with that email address.']);
        }
        $stmt->close();

    } elseif ($step === 2) {
        // Step 2: Verify security answers
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $answer1 = isset($data['answer1']) ? strtolower(trim($data['answer1'])) : '';
        $answer2 = isset($data['answer2']) ? strtolower(trim($data['answer2'])) : '';

        // Verify session matches
        if (!isset($_SESSION['reset_user_id']) || $_SESSION['reset_user_id'] !== $userId || $_SESSION['reset_step'] < 1) {
            echo json_encode(['error' => 'Please start the password reset process from the beginning.']);
            exit();
        }

        if ($userId <= 0 || empty($answer1) || empty($answer2)) {
            echo json_encode(['error' => 'All fields are required']);
            exit();
        }

        // Rate limiting: max 5 attempts per 15 minutes
        $rateLimitKey = 'reset_attempts_' . $userId;
        $attempts = $_SESSION[$rateLimitKey] ?? 0;
        if ($attempts >= 5) {
            echo json_encode(['error' => 'Too many failed attempts. Please try again later.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT security_answer_1, security_answer_2 FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $correct1 = password_verify($answer1, $user['security_answer_1']);
            $correct2 = password_verify($answer2, $user['security_answer_2']);

            if ($correct1 && $correct2) {
                $_SESSION['reset_step'] = 2;
                $_SESSION[$rateLimitKey] = 0; // Reset attempts on success
                echo json_encode(['success' => true, 'message' => 'Security answers verified.']);
            } else {
                $_SESSION[$rateLimitKey] = $attempts + 1;
                echo json_encode(['error' => 'Incorrect security answers. Please try again.']);
            }
        } else {
            echo json_encode(['error' => 'User not found.']);
        }
        $stmt->close();

    } elseif ($step === 3) {
        // Step 3: Reset password (only if step 2 was completed)
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $newPassword = isset($data['new_password']) ? trim($data['new_password']) : '';
        $confirmPassword = isset($data['confirm_new_password']) ? trim($data['confirm_new_password']) : '';

        // Verify session: must have completed step 2
        if (!isset($_SESSION['reset_user_id']) || $_SESSION['reset_user_id'] !== $userId || $_SESSION['reset_step'] < 2) {
            echo json_encode(['error' => 'Please complete security verification first.']);
            exit();
        }

        if ($userId <= 0 || empty($newPassword) || empty($confirmPassword)) {
            echo json_encode(['error' => 'All fields are required']);
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            echo json_encode(['error' => 'Passwords do not match']);
            exit();
        }

        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $newPassword)) {
            echo json_encode(['error' => 'Password must be at least 8 characters and include one uppercase letter, one number, and one special symbol.']);
            exit();
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $passwordHash, $userId);
        
        if ($update->execute()) {
            // Clear reset session
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_step']);
            
            echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now log in with your new password.']);
        } else {
            echo json_encode(['error' => 'Failed to reset password. Please try again.']);
        }
        $update->close();

    } else {
        echo json_encode(['error' => 'Invalid step']);
    }

    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}