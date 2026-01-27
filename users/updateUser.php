<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$data = $_GET;
$targetId = $data['id'] ?? '';
if (!$targetId) jsonError('缺少用户ID');

$userRaw = $db->get($targetId);
if (!$userRaw) jsonError('用户不存在');

$user = json_decode($userRaw, true);

if (isset($data['username']) && $data['username'] !== $user['username']) {
    $newUsername = trim($data['username']);
    if (empty($newUsername)) jsonError('用户名不能为空');

    $existingUserId = $db->get("idx_username_" . md5($newUsername));
    if ($existingUserId && $existingUserId !== $targetId) {
        jsonError('用户名已存在');
    }

    $db->delete("idx_username_" . md5($user['username']));
    $user['username'] = $newUsername;
    $db->set("idx_username_" . md5($newUsername), $targetId);
}

$fields = ['email', 'bio', 'gender', 'qq', 'race', 'age', 'residence', 'role'];
foreach ($fields as $field) {
    if (isset($data[$field])) {
        $user[$field] = trim($data[$field]);
    }
}

$db->set($targetId, json_encode($user));

unset($user['salt']);
unset($user['hash']);
jsonResponse(['success' => true, 'message' => '更新成功', 'user' => $user]);
?>