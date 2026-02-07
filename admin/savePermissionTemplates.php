<?php
require_once '../common.php';
$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        echo json_encode(['success' => true]);
        exit;
    }
    jsonError('Method Not Allowed', 405);
}

// 需要 permissions 模块权限
requireModulePermission($db, 'permissions');

$data = [];

$data = getJsonInput();

if (empty($data) && isset($_REQUEST['payload'])) {
    $data = json_decode($_REQUEST['payload'], true);
}

$templates = $data['templates'] ?? [];

if (!is_array($templates)) {
    $templates = [];
}

$db->set('sys_permission_templates', json_encode($templates));

header('Content-Type: application/json');
echo json_encode(['success' => true, 'count' => count($templates)]);
?>