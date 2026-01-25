<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_file']);
    exit;
}
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$originalName = $_FILES['avatar']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_type']);
    exit;
}
$uploadsDir = 'uploads/avatars';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}
$fileName = uniqid('avatar_', true) . '.' . $extension;
$targetPath = $uploadsDir . '/' . $fileName;
if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'upload_failed']);
    exit;
}
echo json_encode(['url' => 'avatar-file.php?name=' . urlencode($fileName)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
