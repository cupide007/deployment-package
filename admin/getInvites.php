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

// 获取邀请码列表
$inviteListRaw = $db->get('sys_invite_list');
$inviteList = $inviteListRaw ? json_decode($inviteListRaw, true) : [];

$invites = [];
foreach ($inviteList as $code) {
    $inviteRaw = $db->get('invite_' . $code);
    if ($inviteRaw) {
        $invite = json_decode($inviteRaw, true);
        // 计算状态
        $now = time();
        $expiresAt = strtotime($invite['expiresAt']);
        if ($invite['currentUses'] >= $invite['maxUses']) {
            $invite['status'] = 'used';
        } elseif ($now > $expiresAt) {
            $invite['status'] = 'expired';
        } else {
            $invite['status'] = 'active';
        }
        $invites[] = $invite;
    }
}

// 按创建时间倒序
usort($invites, function($a, $b) {
    return strtotime($b['createdAt']) - strtotime($a['createdAt']);
});

jsonResponse([
    'success' => true,
    'invites' => $invites
]);
?>
