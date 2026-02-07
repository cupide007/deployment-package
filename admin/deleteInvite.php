<?php
require_once '../common.php';

$db = new Database('retinbox-main');

// 验证管理员身份
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$userId = json_decode($sessionData, true)['userId'];
$userRaw = $db->get($userId);
if (!$userRaw) jsonError('用户不存在');

$user = json_decode($userRaw, true);
if (($user['role'] ?? '') !== 'admin') {
    jsonError('无权限操作', 403);
}

$data = getJsonInput();
if (empty($data)) $data = $_GET;

$code = $data['code'] ?? '';
if (!$code) jsonError('缺少邀请码');

// 删除邀请码
$db->delete('invite_' . $code);

// 从列表中移除
$inviteListRaw = $db->get('sys_invite_list');
$inviteList = $inviteListRaw ? json_decode($inviteListRaw, true) : [];
$inviteList = array_filter($inviteList, function($c) use ($code) {
    return $c !== $code;
});
$db->set('sys_invite_list', json_encode(array_values($inviteList)));

jsonResponse([
    'success' => true,
    'message' => '邀请码已删除'
]);
?>
