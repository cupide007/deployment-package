<?php
if (!isset($_GET['name'])) {
    http_response_code(400);
    exit;
}
$name = basename($_GET['name']);
$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($extension, $allowed, true)) {
    http_response_code(400);
    exit;
}
$documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$filePath = $documentRoot . '/uploads/avatars/' . $name;
if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit;
}
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];
$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $contentType);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=31536000');
clearstatcache(true, $filePath);
$fileSize = filesize($filePath);
if ($fileSize === false) {
    http_response_code(500);
    exit;
}
header('Content-Length: ' . $fileSize);
$result = readfile($filePath);
if ($result === false) {
    http_response_code(500);
}
