<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$section = isset($_POST['section']) ? trim($_POST['section']) : '';
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$subtitle = isset($_POST['subtitle']) ? trim($_POST['subtitle']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$metaJson = isset($_POST['meta']) ? $_POST['meta'] : '{}';

$allowed = ['homepage_hero', 'about_us', 'contact_info', 'help_center'];
if (!in_array($section, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid section']);
    exit;
}

// Validate meta JSON
$meta = json_decode($metaJson, true);
if ($meta === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid meta JSON']);
    exit;
}

// Handle image upload
$imageUrl = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($_FILES['image']['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid image type. Use JPG, PNG, WebP, or GIF.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../assets/content/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = $section . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        @chmod($dest, 0644);
        $imageUrl = 'assets/content/' . $filename;
    }
}

// Check if section exists
$check = $conn->prepare("SELECT id FROM site_content WHERE section_key = ?");
$check->bind_param('s', $section);
$check->execute();
$check->store_result();
$exists = $check->num_rows > 0;
$check->close();

if ($exists) {
    if ($imageUrl) {
        $stmt = $conn->prepare("UPDATE site_content SET title = ?, subtitle = ?, content = ?, image_url = ?, meta = ?, updated_at = NOW() WHERE section_key = ?");
        $stmt->bind_param('ssssss', $title, $subtitle, $content, $imageUrl, $metaJson, $section);
    } else {
        $stmt = $conn->prepare("UPDATE site_content SET title = ?, subtitle = ?, content = ?, meta = ?, updated_at = NOW() WHERE section_key = ?");
        $stmt->bind_param('sssss', $title, $subtitle, $content, $metaJson, $section);
    }
} else {
    if ($imageUrl) {
        $stmt = $conn->prepare("INSERT INTO site_content (section_key, title, subtitle, content, image_url, meta, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('ssssss', $section, $title, $subtitle, $content, $imageUrl, $metaJson);
    } else {
        $stmt = $conn->prepare("INSERT INTO site_content (section_key, title, subtitle, content, meta, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssss', $section, $title, $subtitle, $content, $metaJson);
    }
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Content saved successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save content']);
}

$stmt->close();
$conn->close();
