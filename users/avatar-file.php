<?php
$name = basename($_GET['name'] ?? 'default.png');
$filePath = __DIR__ . '/../uploads/avatars/' . $name;

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('Not Found');
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'
];
header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
readfile($filePath);
?>