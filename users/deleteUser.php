<?php
require_once '../common.php';

$db = new Database('retinbox-main');

// 需要 users 模块权限
requireModulePermission($db, 'users');

$userId = $_GET['id'] ?? '';
if (!$userId) jsonError('缺少用户ID');

$userRaw = $db->get($userId);
if (!$userRaw) jsonError('用户不存在');

$user = json_decode($userRaw, true);

if (isset($user['username'])) {
    $db->delete("idx_username_" . md5($user['username']));
}

// 删除邮箱索引
if (isset($user['email'])) {
    $db->delete("idx_email_" . md5($user['email']));
}

// 从用户列表移除
$usersListRaw = $db->get('sys_users_list');
$usersList = $usersListRaw ? json_decode($usersListRaw, true) : [];
$usersList = array_filter($usersList, fn($uid) => $uid !== $userId);
$db->set('sys_users_list', json_encode(array_values($usersList)));

// 删除用户主数据
$db->delete($userId);

// 删除银行账户和卡片索引
$accountRaw = $db->get("bank_account_" . $userId);
if ($accountRaw) {
    $account = json_decode($accountRaw, true);
    if (isset($account['cards'])) {
        foreach ($account['cards'] as $card) {
            if (isset($card['cardNumber'])) {
                $db->delete('idx_card_' . $card['cardNumber']);
            }
        }
    }
}
$db->delete("bank_account_" . $userId);

// 删除交易记录
$db->delete("bank_tx_" . $userId);

// 删除用户设置
$db->delete("user_settings_" . $userId);

// 删除头像数据
$avatarMetaRaw = $db->get('avatar_meta_' . $userId);
if ($avatarMetaRaw) {
    $avatarMeta = json_decode($avatarMetaRaw, true);
    for ($i = 0; $i < ($avatarMeta['count'] ?? 0); $i++) {
        $db->delete('avatar_chunk_' . $userId . '_' . $i);
    }
    $db->delete('avatar_meta_' . $userId);
}

jsonResponse(['success' => true, 'message' => '用户删除成功']);
?>