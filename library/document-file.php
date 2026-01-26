<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$docId = $_GET['id'] ?? '';
if (!$docId) {
    http_response_code(400);
    exit('Missing ID');
}

$metaRaw = $db->get("meta_{$docId}");
if (!$metaRaw) {
    $listRaw = $db->get("lib_documents_global");
    $list = $listRaw ? json_decode($listRaw, true) : [];
    $found = null;
    foreach ($list as $d) {
        if ($d['id'] === $docId) {
            $found = $d;
            break;
        }
    }
    if (!$found) {
        http_response_code(404);
        exit('Document Not Found');
    }
    $meta = $found;
} else {
    $meta = json_decode($metaRaw, true);
}

$base64 = '';
for ($i = 0; $i < $meta['chunk_count']; $i++) {
    $chunk = $db->get("chunk_{$docId}_{$i}");
    if ($chunk === false) {
        http_response_code(500);
        exit("Chunk missing");
    }
    $base64 .= $chunk;
}

$content = base64_decode($base64);

header('Content-Type: ' . ($meta['type'] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . rawurlencode($meta['name']) . '"');
header('Content-Length: ' . strlen($content));
echo $content;
?>