<?php
session_start();

// Only sellers can access this
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once '../db-config.php';

$sellerId = $_SESSION['user_id'];

// Initialize response
$response = ['success' => false, 'error' => ''];

try {
    // Validate required fields
    $requiredFields = ['store_name', 'username', 'name', 'email', 'contact_number'];
    foreach ($requiredFields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }

    // Validate email format
    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email is already used by another user
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $checkStmt->bind_param('si', $email, $sellerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult && $checkResult->num_rows > 0) {
        throw new Exception('This email address is already used by another account');
    }
    $checkStmt->close();

    // Handle profile photo upload
    $profilePhotoPath = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imageFile = $_FILES['profile_photo'];
        if ($imageFile['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $imageInfo = getimagesize($imageFile['tmp_name']);
            
            if ($imageInfo === false || !isset($allowedTypes[$imageInfo['mime']])) {
                throw new Exception('Please upload a valid image file (JPG, PNG, GIF, WEBP)');
            }

            $extension = $allowedTypes[$imageInfo['mime']];
            $filename = 'profile_' . $sellerId . '_' . time() . '.' . $extension;
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetPath = $uploadDir . $filename;
            
            if (!move_uploaded_file($imageFile['tmp_name'], $targetPath)) {
                throw new Exception('Failed to upload profile photo');
            }

            $profilePhotoPath = 'uploads/profiles/' . $filename;

            // Delete old profile photo if exists
            $oldPhotoStmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $oldPhotoStmt->bind_param('i', $sellerId);
            $oldPhotoStmt->execute();
            $oldPhotoResult = $oldPhotoStmt->get_result();
            if ($oldPhotoRow = $oldPhotoResult->fetch_assoc()) {
                if (!empty($oldPhotoRow['profile_photo']) && file_exists('../' . $oldPhotoRow['profile_photo'])) {
                    unlink('../' . $oldPhotoRow['profile_photo']);
                }
            }
            $oldPhotoStmt->close();
        } else {
            throw new Exception('Image upload failed. Please try again.');
        }
    }

    // Prepare update query
    $updateFields = [
        'store_name' => $_POST['store_name'] ?? '',
        'username' => $_POST['username'] ?? '',
        'name' => $_POST['name'] ?? '',
        'email' => $email,
        'contact_number' => $_POST['contact_number'] ?? '',
        'gender' => $_POST['gender'] ?? null,
        'date_of_birth' => $_POST['date_of_birth'] ?? null,
        'business_name' => $_POST['business_name'] ?? null,
        'business_address' => $_POST['business_address'] ?? null,
        'tax_information' => $_POST['tax_information'] ?? null,
        'bank_account' => $_POST['bank_account'] ?? null,
        'payment_methods' => $_POST['payment_methods'] ?? null,
        'notification_preferences' => $_POST['notification_preferences'] ?? 'email',
        'language' => $_POST['language'] ?? 'en',
        'timezone' => $_POST['timezone'] ?? 'Asia/Manila',
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Add profile photo path if uploaded
    if ($profilePhotoPath) {
        $updateFields['profile_photo'] = $profilePhotoPath;
    }

    // Build dynamic UPDATE query
    $setClauses = [];
    $params = [];
    $types = '';
    
    foreach ($updateFields as $field => $value) {
        $setClauses[] = "$field = ?";
        $params[] = $value;
        $types .= 's';
    }
    
    $params[] = $sellerId;
    $types .= 'i';
    
    $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $updateStmt = $conn->prepare($sql);
    
    if (!$updateStmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $updateStmt->bind_param($types, ...$params);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update profile: ' . $updateStmt->error);
    }
    
    $updateStmt->close();

    // Update session variables
    $_SESSION['user_name'] = $updateFields['name'];
    $_SESSION['user_email'] = $updateFields['email'];

    // Log activity
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'profile_update', 'Seller updated profile information', ?)");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logStmt->bind_param('is', $sellerId, $ipAddress);
    $logStmt->execute();
    $logStmt->close();

    $response['success'] = true;
    $response['message'] = 'Profile updated successfully';

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
