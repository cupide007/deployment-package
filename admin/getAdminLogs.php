<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 permissions 模块权限（查看日志）
requireModulePermission($db, 'permissions');

$logsRaw = $db->get('sys_admin_logs');
$logs = $logsRaw ? json_decode($logsRaw, true) : [];

jsonResponse(['success' => true, 'logs' => $logs]);
?>