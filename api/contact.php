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
    
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    $product_name = isset($data['product_name']) ? trim($data['product_name']) : '';
    $quantity = isset($data['quantity']) ? max(1, (int) $data['quantity']) : 1;
    
    // Validation
    if (empty($name) || empty($email) || empty($message)) {
        if ($isJson) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            exit();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please complete all required fields.'];
        header('Location: ../index.php#contact');
        exit();
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($isJson) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email']);
            exit();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email address.'];
        header('Location: ../index.php#contact');
        exit();
    }
    
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        if ($isJson) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $conn->error]);
            exit();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Database error. Please try again.'];
        header('Location: ../index.php#contact');
        exit();
    }
    
    $stmt->bind_param("sss", $name, $email, $message);
    
    if ($stmt->execute()) {
        $contactId = $stmt->insert_id;

        if (!empty($product_name)) {
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $insertRequest = $conn->prepare("INSERT INTO customization_requests (user_id, product_name, quantity, message, status) VALUES (?, ?, ?, ?, 'pending')");
            if ($insertRequest) {
                $insertRequest->bind_param("isis", $user_id, $product_name, $quantity, $message);
                $insertRequest->execute();
                $insertRequest->close();
            }

            $insertNotification = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, related_type, related_id) VALUES (?, ?, ?, ?, 'customization', ?)");
            if ($insertNotification) {
                $notifTitle = 'New customization request';
                $notifBody = 'A customer requested customization for ' . $product_name . '. Quantity: ' . $quantity;
                $relatedId = $contactId;
                $type = 'customization_request';
                $insertNotification->bind_param("isssi", $user_id, $type, $notifTitle, $notifBody, $relatedId);
                $insertNotification->execute();
                $insertNotification->close();
            }
        }

        if ($isJson) {
            echo json_encode(['ok' => true, 'id' => $contactId, 'message' => 'Thank you for your message!']);
        } else {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Thank you for your message!'];
            header('Location: ../index.php#contact');
            exit();
        }
    } else {
        if ($isJson) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save contact']);
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to save message. Please try again.'];
            header('Location: ../index.php#contact');
            exit();
        }
    }
    
    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>


