<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

$data = getJsonInput();
$userRaw = $db->get($userId);
if (!$userRaw) jsonError('用户不存在');

$user = json_decode($userRaw, true);

if (isset($data['username']) && $data['username'] !== $user['username']) {
    $newUsername = trim($data['username']);
    if ($db->get("idx_username_" . md5($newUsername))) {
        jsonError('用户名已存在');
    }
    $db->delete("idx_username_" . md5($user['username']));
    $user['username'] = $newUsername;
    $db->set("idx_username_" . md5($newUsername), $userId);
}

if (isset($data['bio'])) $user['bio'] = strip_tags($data['bio']);
if (isset($data['gender'])) $user['gender'] = $data['gender'];

$db->set($userId, json_encode($user));

unset($user['salt']);
unset($user['hash']);
jsonResponse(['success' => true, 'message' => '更新成功', 'user' => $user]);
?>