<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 permissions 模块权限
requireModulePermission($db, 'permissions');

$data = getJsonInput();
$id = $data['id'] ?? $_GET['id'] ?? '';

if (!$id) jsonError('缺少参数 ID');

$permissionsRaw = $db->get('sys_admin_permissions');
$permissionsList = $permissionsRaw ? json_decode($permissionsRaw, true) : [];

$found = false;
$targetUserId = null;
$adminName = '';
$newList = [];

foreach ($permissionsList as $p) {
    if ($p['id'] === $id) {
        $found = true;
        $targetUserId = $p['userId'];
        $adminName = $p['adminName'] ?? '未知管理员';
    } else {
        $newList[] = $p;
    }
}

if (!$found) jsonError('未找到该权限记录', 404);

$db->set('sys_admin_permissions', json_encode($newList));

$hasOtherPositions = false;
foreach ($newList as $p) {
    if ($p['userId'] === $targetUserId) {
        $hasOtherPositions = true;
        break;
    }
}

if (!$hasOtherPositions && $targetUserId) {
    $userRaw = $db->get($targetUserId);
    if ($userRaw) {
        $user = json_decode($userRaw, true);
        if (($user['role'] ?? '') === 'admin') {
            $user['role'] = 'user'; // 降级
            $db->set($targetUserId, json_encode($user));
        }
    }
}

$operatorName = 'System';
$sessionData = $db->get('sess_' . $sessionId);
if ($sessionData) {
    $sData = json_decode($sessionData, true);
    $opRaw = $db->get($sData['userId']);
    if ($opRaw) {
        $opUser = json_decode($opRaw, true);
        $operatorName = $opUser['username'];
    }
}

$logData = [
    'id' => 'log_' . uniqid(),
    'adminName' => $operatorName,
    'action' => 'delete_admin',
    'details' => "撤销管理员: {$adminName} " . ($hasOtherPositions ? "(仍保留其他职位)" : "(已降级为普通用户)"),
    'timestamp' => date('Y-m-d H:i:s')
];

$logsRaw = $db->get('sys_admin_logs');
$logsList = $logsRaw ? json_decode($logsRaw, true) : [];
array_unshift($logsList, $logData);
if (count($logsList) > 500) $logsList = array_slice($logsList, 0, 500);
$db->set('sys_admin_logs', json_encode($logsList));

jsonResponse(['success' => true]);
?>