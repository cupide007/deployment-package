<?php
/**
 * 获取当前登录用户自己的权限配置
 * 这个接口不需要 permissions 模块权限，任何登录的管理员都可以调用
 */
require_once '../common.php';
$db = new Database('retinbox-main');

// 只需要验证登录
$user = getCurrentUser($db);
if (!$user) {
    jsonError('未登录', 401);
}

// 获取权限列表
$permissionsRaw = $db->get('sys_admin_permissions');
$permissions = $permissionsRaw ? json_decode($permissionsRaw, true) : [];

// 查找当前用户的权限配置
$myPermission = null;
foreach ($permissions as $perm) {
    if ($perm['userId'] === $user['id']) {
        $myPermission = $perm;
        break;
    }
}

// 如果没有配置权限，默认拥有所有模块（向后兼容）
if (!$myPermission && $user['role'] === 'admin') {
    $myPermission = [
        'modules' => ['dashboard', 'users', 'bank', 'library', 'permissions']
    ];
}

jsonResponse([
    'success' => true,
    'permission' => $myPermission,
    'modules' => $myPermission ? $myPermission['modules'] : []
]);
?>
