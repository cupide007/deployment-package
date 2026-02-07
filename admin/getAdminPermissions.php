<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 permissions 模块权限
requireModulePermission($db, 'permissions');

$permsRaw = $db->get('sys_admin_permissions');
$permissions = $permsRaw ? json_decode($permsRaw, true) : [];

// 同时返回用户列表，用于权限管理的用户选择（避免依赖 users 模块权限）
$usersList = json_decode($db->get('sys_users_list') ?: '[]', true);
$usersForPermissions = [];
foreach ($usersList as $uid) {
    $uRaw = $db->get($uid);
    if ($uRaw) {
        $uInfo = json_decode($uRaw, true);
        $usersForPermissions[] = [
            'id' => $uid,
            'username' => $uInfo['username'] ?? '未知',
            'email' => $uInfo['email'] ?? ''
        ];
    }
}

jsonResponse([
    'success' => true, 
    'permissions' => $permissions,
    'users' => $usersForPermissions // 用于权限管理的用户选择列表
]);
?>