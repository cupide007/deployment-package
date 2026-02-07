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

// 生成邀请码
$code = strtoupper(bin2hex(random_bytes(4))); // 8位随机码

$data = getJsonInput();
if (empty($data)) $data = $_GET;

$maxUses = intval($data['maxUses'] ?? 1);
$expiresIn = intval($data['expiresIn'] ?? 7); // 默认7天后过期

$invite = [
    'code' => $code,
    'createdBy' => $userId,
    'createdByName' => $user['username'],
    'createdAt' => date('Y-m-d H:i:s'),
    'maxUses' => $maxUses,
    'currentUses' => 0,
    'usedBy' => [],
    'expiresAt' => date('Y-m-d H:i:s', time() + ($expiresIn * 86400))
];

// 存储邀请码
$db->set('invite_' . $code, json_encode($invite));

// 添加到邀请码列表
$inviteListRaw = $db->get('sys_invite_list');
$inviteList = $inviteListRaw ? json_decode($inviteListRaw, true) : [];
$inviteList[] = $code;
$db->set('sys_invite_list', json_encode($inviteList));

jsonResponse([
    'success' => true,
    'message' => '邀请码生成成功',
    'invite' => $invite
]);
?>
