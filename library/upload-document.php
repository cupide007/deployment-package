<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    jsonError('上传失败');
}

$file = $_FILES['document'];
$docId = uniqid('doc_');
$content = file_get_contents($file['tmp_name']);
$base64 = base64_encode($content);
$chunks = str_split($base64, 50000);

foreach ($chunks as $index => $chunk) {
    $db->set("chunk_{$docId}_{$index}", $chunk);
}

$newDoc = [
    'id' => $docId,
    'name' => $file['name'],
    'type' => $file['type'],
    'size' => $file['size'],
    'chunk_count' => count($chunks),
    'uploaded_at' => date('c'),
    'uploader_id' => $userId
];

$listRaw = $db->get("lib_documents_global");
$documents = $listRaw ? json_decode($listRaw, true) : [];
array_unshift($documents, $newDoc);
$db->set("lib_documents_global", json_encode($documents));
$db->set("meta_{$docId}", json_encode($newDoc));

jsonResponse(['success' => true, 'document' => $newDoc]);
?>