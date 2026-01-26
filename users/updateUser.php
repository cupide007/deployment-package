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

// 修改用户名
if (isset($data['username']) && $data['username'] !== $user['username']) {
    $newUsername = trim($data['username']);
    if (empty($newUsername)) jsonError('用户名不能为空');
    if ($db->get("idx_username_" . md5($newUsername))) {
        jsonError('用户名已存在');
    }
    $db->delete("idx_username_" . md5($user['username']));
    $user['username'] = $newUsername;
    $db->set("idx_username_" . md5($newUsername), $userId);
}

// 批量处理普通字段
$fields = ['bio', 'gender', 'qq', 'race', 'age', 'residence'];
foreach ($fields as $field) {
    if (isset($data[$field])) {
        $user[$field] = trim($data[$field]);
    }
}

$db->set($userId, json_encode($user));

unset($user['salt']);
unset($user['hash']);
jsonResponse(['success' => true, 'message' => '更新成功', 'user' => $user]);
?>