<?php
require_once '../common.php';
$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('Unauthorized', 401);

$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('Session Expired', 401);
$userId = json_decode($sessionData, true)['userId'];

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No file');
}

$file = $_FILES['avatar'];
$mime = mime_content_type($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed)) {
    jsonError('Invalid type');
}

$content = file_get_contents($file['tmp_name']);
$base64 = base64_encode($content);
$chunks = str_split($base64, 50000);
$count = count($chunks);

$oldMetaRaw = $db->get('avatar_meta_' . $userId);
if ($oldMetaRaw) {
    $oldMeta = json_decode($oldMetaRaw, true);
    for ($i = 0; $i < $oldMeta['count']; $i++) {
        $db->delete('avatar_chunk_' . $userId . '_' . $i);
    }
}

foreach ($chunks as $index => $chunk) {
    $db->set('avatar_chunk_' . $userId . '_' . $index, $chunk);
}

$meta = ['mime' => $mime, 'count' => $count];
$db->set('avatar_meta_' . $userId, json_encode($meta));

$userRaw = $db->get($userId);
$user = json_decode($userRaw, true);
$user['avatar'] = 'DB:' . $userId;
$db->set($userId, json_encode($user));

jsonResponse(['success' => true, 'url' => $user['avatar']]);
?>