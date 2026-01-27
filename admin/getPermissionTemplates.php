<?php
require_once '../common.php';
$db = new Database('retinbox-main');
$templates = $db->get('permission_templates_global');

if (!$templates) {
    $default = [
        ['id' => 't1', 'name' => '全局管理员', 'level' => 'global_admin', 'modules' => ['dashboard', 'users', 'bank', 'library', 'permissions']],
        ['id' => 't2', 'name' => '图书馆管理员', 'level' => 'module_admin', 'modules' => ['dashboard', 'library']]
    ];
    echo json_encode(['success' => true, 'templates' => $default]);
} else {
    echo json_encode(['success' => true, 'templates' => json_decode($templates, true)]);
}
?>