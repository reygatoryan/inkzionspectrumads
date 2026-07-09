<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../db-config.php';

$section = isset($_GET['section']) ? trim($_GET['section']) : '';

if ($section) {
    $stmt = $conn->prepare("SELECT section_key, title, subtitle, content, image_url, meta FROM site_content WHERE section_key = ?");
    $stmt->bind_param('s', $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Section not found']);
        exit;
    }
    if ($row['meta']) {
        $row['meta'] = json_decode($row['meta'], true);
    }
    echo json_encode(['success' => true, 'data' => $row]);
} else {
    $result = $conn->query("SELECT section_key, title, subtitle, content, image_url, meta FROM site_content ORDER BY id ASC");
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        if ($row['meta']) {
            $row['meta'] = json_decode($row['meta'], true);
        }
    }
    echo json_encode(['success' => true, 'data' => $rows]);
}
$conn->close();
