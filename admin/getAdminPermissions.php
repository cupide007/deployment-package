<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 permissions 模块权限
requireModulePermission($db, 'permissions');

$permsRaw = $db->get('sys_admin_permissions');
$permissions = $permsRaw ? json_decode($permsRaw, true) : [];

jsonResponse(['success' => true, 'permissions' => $permissions]);
?>