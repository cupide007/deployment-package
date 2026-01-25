<?php
if (!isset($_GET['name'])) {
    http_response_code(400);
    exit;
}
$name = basename($_GET['name']);
$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'md'];
if (!in_array($extension, $allowed, true)) {
    http_response_code(400);
    exit;
}
$rootDir = $_SERVER['DOCUMENT_ROOT'] ?? '';
$rootDir = is_string($rootDir) ? rtrim($rootDir, '/') : '';
$filePath = $rootDir !== '' ? $rootDir . '/uploads/documents/' . $name : 'uploads/documents/' . $name;
if (!is_file($filePath) || !is_readable($filePath)) {
    $fallbackPath = 'uploads/documents/' . $name;
    if (is_file($fallbackPath) && is_readable($fallbackPath)) {
        $filePath = $fallbackPath;
    }
}
if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit;
}
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain',
    'md' => 'text/markdown'
];
$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $contentType);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=31536000');
if (isset($_GET['download'])) {
    $safeName = str_replace('"', '', $name);
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
}
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
