<?php
require_once '../common.php';
$db = new Database('retinbox-main');
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['adminId'])) {
    echo json_encode(['success' => false, 'error' => 'Data incomplete']);
    exit;
}

$permissions = $db->get('admin_permissions_global');
$permissionsList = $permissions ? json_decode($permissions, true) : [];

$found = false;
foreach ($permissionsList as &$p) {
    if ($p['adminId'] === $data['adminId']) {
        $p = array_merge($p, $data);
        $found = true;
        break;
    }
}

if (!$found) {
    $data['id'] = 'perm_' . uniqid();
    $permissionsList[] = $data;
}

$db->set('admin_permissions_global', json_encode($permissionsList));
echo json_encode(['success' => true]);
?>