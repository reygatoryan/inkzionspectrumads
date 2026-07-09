<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db-config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all addresses for the user
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'addresses' => $addresses]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $addressId = isset($data['address_id']) ? (int)$data['address_id'] : 0;
    
    if (!$addressId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Address ID required']);
        exit;
    }
    
    // Verify ownership
    $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $addressId, $userId);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    
    if ($deleted > 0) {
        echo json_encode(['success' => true, 'message' => 'Address deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Address not found']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    
    $addressId = isset($data['address_id']) ? (int)$data['address_id'] : 0;
    $label = isset($data['label']) ? trim($data['label']) : 'Home';
    $firstName = isset($data['first_name']) ? trim($data['first_name']) : '';
    $lastName = isset($data['last_name']) ? trim($data['last_name']) : '';
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    $street = isset($data['street']) ? trim($data['street']) : '';
    $barangay = isset($data['barangay']) ? trim($data['barangay']) : '';
    $city = isset($data['city']) ? trim($data['city']) : '';
    $province = isset($data['province']) ? trim($data['province']) : '';
    $zipCode = isset($data['zip_code']) ? trim($data['zip_code']) : '';
    $country = isset($data['country']) ? trim($data['country']) : 'Philippines';
    $isDefault = isset($data['is_default']) ? (int)$data['is_default'] : 0;
    
    if (empty($firstName) || empty($lastName) || empty($phone) || empty($street) || empty($city) || empty($province) || empty($zipCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required address fields']);
        exit;
    }
    
    if ($isDefault) {
        // Remove default from all other addresses
        $conn->query("UPDATE user_addresses SET is_default = 0 WHERE user_id = $userId");
    }
    
    if ($addressId > 0) {
        // Update existing address
        $stmt = $conn->prepare("UPDATE user_addresses SET label=?, first_name=?, last_name=?, phone=?, street=?, barangay=?, city=?, province=?, zip_code=?, country=?, is_default=? WHERE id=? AND user_id=?");
        $stmt->bind_param('ssssssssssiii', $label, $firstName, $lastName, $phone, $street, $barangay, $city, $province, $zipCode, $country, $isDefault, $addressId, $userId);
    } else {
        // Insert new address
        $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, label, first_name, last_name, phone, street, barangay, city, province, zip_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssssssi', $userId, $label, $firstName, $lastName, $phone, $street, $barangay, $city, $province, $zipCode, $country, $isDefault);
    }
    
    if ($stmt->execute()) {
        $newId = $addressId > 0 ? $addressId : $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => $addressId > 0 ? 'Address updated' : 'Address added', 'address_id' => $newId]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save address']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
