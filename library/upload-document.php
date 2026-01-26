<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('Unauthorized', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('Session Expired', 401);
$userId = json_decode($sessionData, true)['userId'];

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    jsonError('File upload failed');
}

$title = $_POST['title'] ?? '';
$author = $_POST['author'] ?? '';
$category = $_POST['category'] ?? '其他';
$description = $_POST['description'] ?? '';

if (!$title || !$author) {
    jsonError('Title and Author are required');
}

$file = $_FILES['document'];
$docId = uniqid('doc_');
$content = file_get_contents($file['tmp_name']);
$base64 = base64_encode($content);
$chunks = str_split($base64, 50000);

foreach ($chunks as $index => $chunk) {
    $db->set("chunk_{$docId}_{$index}", $chunk);
}

$userRaw = $db->get($userId);
$uploaderName = $userRaw ? json_decode($userRaw, true)['username'] : 'Unknown';

$newDoc = [
    'id' => $docId,
    'title' => $title,
    'author' => $author,
    'category' => $category,
    'description' => $description,
    'name' => $file['name'],
    'type' => $file['type'],
    'size' => $file['size'],
    'chunk_count' => count($chunks),
    'uploadDate' => date('c'),
    'uploaderId' => $userId,
    'uploaderName' => $uploaderName,
    'approved' => true,
    'fileUrl' => 'library/document-file.php?id=' . $docId
];

$listRaw = $db->get("lib_documents_global");
$documents = $listRaw ? json_decode($listRaw, true) : [];
array_unshift($documents, $newDoc);
$db->set("lib_documents_global", json_encode($documents));
$db->set("meta_{$docId}", json_encode($newDoc));

jsonResponse(['success' => true, 'document' => $newDoc]);
?>