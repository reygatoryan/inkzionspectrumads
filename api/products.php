<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../db-config.php';

$category = isset($_GET['category']) ? $_GET['category'] : null;

if ($category) {
    $stmt = $conn->prepare("SELECT p.*, c.name as category FROM products p 
                          JOIN categories c ON p.category_id = c.id 
                          WHERE c.name = ? ORDER BY p.name");
    $stmt->bind_param("s", $category);
} else {
    $stmt = $conn->prepare("SELECT p.*, c.name as category FROM products p 
                          JOIN categories c ON p.category_id = c.id 
                          ORDER BY p.name");
}

$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode(['products' => $products]);

$stmt->close();
$conn->close();
?>

