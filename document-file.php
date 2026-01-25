<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');

$docId = $_GET['id'] ?? '';
if (!preg_match('/^[a-f0-9]+$/', $docId)) {
    http_response_code(400);
    exit;
}

try {
    if (!class_exists('Database')) {
    }

    $db = new Database('antister_virtual_country');

    $metaKey = "meta_{$docId}";
    $json = $db->get($metaKey);

    if (!$json) {
        http_response_code(404);
        exit;
    }

    $data = json_decode($json, true);
    if (!isset($data['chunk_count'])) {
        http_response_code(500);
        exit;
    }

    $base64 = '';
    for ($i = 0; $i < $data['chunk_count']; $i++) {
        $chunkKey = "chunk_{$docId}_{$i}";
        $chunk = $db->get($chunkKey);
        if ($chunk === false) {
            http_response_code(500);
            exit;
        }
        $base64 .= $chunk;
    }

    $content = base64_decode($base64);
    $size = strlen($content);

    $fileName = $data['name'] ?? 'file.bin';
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $contentType = 'application/octet-stream';
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'md'  => 'text/markdown',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];

    if (isset($mimeTypes[$ext])) {
        $contentType = $mimeTypes[$ext];
    } else if (isset($data['type'])) {
        $contentType = $data['type'];
    }

    $encodedName = rawurlencode($fileName);

    header("Content-Type: $contentType");
    header("Content-Length: $size");
    header("Content-Disposition: inline; filename=\"$encodedName\"; filename*=UTF-8''$encodedName");
    header('Cache-Control: public, max-age=31536000');

    echo $content;
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    exit;
}
?>