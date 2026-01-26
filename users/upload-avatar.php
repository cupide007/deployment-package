<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    jsonError('请选择图片');
}

$file = $_FILES['avatar'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    jsonError('不支持的格式');
}

$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
    jsonError('上传失败', 500);
}

$userRaw = $db->get($userId);
$user = json_decode($userRaw, true);
$user['avatar'] = $fileName;
$db->set($userId, json_encode($user));

jsonResponse(['success' => true, 'url' => $fileName]);
?>