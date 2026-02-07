<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$currentUserId = json_decode($sessionData, true)['userId'];
$currentUserRaw = $db->get($currentUserId);
$currentUserData = $currentUserRaw ? json_decode($currentUserRaw, true) : [];
$isAdmin = ($currentUserData['role'] ?? '') === 'admin';

$data = $_GET;
$targetId = $data['id'] ?? '';
if (!$targetId) jsonError('缺少用户ID');

// 权限检查：只能修改自己，或管理员可修改任何人
if ($targetId !== $currentUserId && !$isAdmin) {
    jsonError('无权修改其他用户信息', 403);
}

$userRaw = $db->get($targetId);
if (!$userRaw) jsonError('用户不存在');

$user = json_decode($userRaw, true);

// 处理用户名变更
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

// 处理邮箱变更
if (isset($data['email']) && trim($data['email']) !== ($user['email'] ?? '')) {
    $newEmail = trim($data['email']);
    if (!empty($newEmail)) {
        $existingUserId = $db->get("idx_email_" . md5($newEmail));
        if ($existingUserId && $existingUserId !== $targetId) {
            jsonError('邮箱已被使用');
        }
        // 删除旧邮箱索引
        if (!empty($user['email'])) {
            $db->delete("idx_email_" . md5($user['email']));
        }
        // 创建新邮箱索引
        $db->set("idx_email_" . md5($newEmail), $targetId);
    }
    $user['email'] = $newEmail;
}

// 普通字段更新
$fields = ['bio', 'gender', 'qq', 'race', 'age', 'residence'];
foreach ($fields as $field) {
    if (isset($data[$field])) {
        $user[$field] = trim($data[$field]);
    }
}

// role 只有管理员可以修改
if (isset($data['role']) && $isAdmin) {
    $user['role'] = trim($data['role']);
}

$db->set($targetId, json_encode($user));

unset($user['salt']);
unset($user['hash']);
jsonResponse(['success' => true, 'message' => '更新成功', 'user' => $user]);
?>