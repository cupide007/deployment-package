<?php
\RthRunner\Runtime::require_once_method('../common.php');
$db = new Database('retinbox-main');

$templatesRaw = $db->get('sys_permission_templates');

if (!$templatesRaw) {
    $default = [
        [
            'id' => 't1',
            'name' => '全局管理员',
            'level' => 'global_admin',
            'modules' => ['dashboard', 'users', 'bank', 'library', 'permissions']
        ],
        [
            'id' => 't2',
            'name' => '图书馆管理员',
            'level' => 'module_admin',
            'modules' => ['dashboard', 'library']
        ]
    ];
    $db->set('sys_permission_templates', json_encode($default));
    echo json_encode(['success' => true, 'templates' => $default]);
} else {
    echo json_encode(['success' => true, 'templates' => json_decode($templatesRaw, true)]);
}
?>