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

$data = getJsonInput();
$currentPassword = $data['currentPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';

if (!$currentPassword || !$newPassword) {
    jsonError('请输入当前密码和新密码');
}

$userRaw = $db->get($userId);
if (!$userRaw) jsonError('用户不存在');
$user = json_decode($userRaw, true);

$verifyHash = hash_pbkdf2('sha512', $currentPassword, $user['salt'], 1000, 64);
if ($verifyHash !== $user['hash']) {
    jsonError('当前密码错误');
}

$newSalt = bin2hex(random_bytes(16));
$newHash = hash_pbkdf2('sha512', $newPassword, $newSalt, 1000, 64);

$user['salt'] = $newSalt;
$user['hash'] = $newHash;
$db->set($userId, json_encode($user));

jsonResponse(['success' => true, 'message' => '密码修改成功']);
?>