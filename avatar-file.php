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
$filePath = 'uploads/avatars/' . $name;
if (!is_file($filePath)) {
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
header('Cache-Control: public, max-age=31536000');
readfile($filePath);
