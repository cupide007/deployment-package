<?php
require_once '../common.php';
$db = new Database('retinbox-main');

header("Access-Control-Allow-Origin: *");

$name = $_GET['name'] ?? '';

if (strpos($name, 'DB:') === 0) {
    $userId = substr($name, 3);
    $metaRaw = $db->get('avatar_meta_' . $userId);

    if (!$metaRaw) {
        http_response_code(404);
        exit;
    }

    $meta = json_decode($metaRaw, true);
    header('Content-Type: ' . $meta['mime']);

    for ($i = 0; $i < $meta['count']; $i++) {
        echo base64_decode($db->get('avatar_chunk_' . $userId . '_' . $i));
    }
} else {
    $file = __DIR__ . '/../uploads/avatars/' . basename($name);
    if (file_exists($file)) {
        $info = getimagesize($file);
        header('Content-Type: ' . $info['mime']);
        readfile($file);
    } else {
        http_response_code(404);
    }
}
?>