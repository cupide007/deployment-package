<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_file']);
    exit;
}
$allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'md'];
$originalName = $_FILES['document']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_type']);
    exit;
}
$uploadsDir = __DIR__ . '/uploads/documents';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}
$fileName = uniqid('document_', true) . '.' . $extension;
$targetPath = $uploadsDir . '/' . $fileName;
if (!move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'upload_failed']);
    exit;
}
$publicUrl = 'document-file.php?name=' . urlencode($fileName);
echo json_encode(['url' => $publicUrl], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
