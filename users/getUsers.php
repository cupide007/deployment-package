<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 users 模块权限
requireModulePermission($db, 'users');

$listRaw = $db->get('sys_users_list');
$idList = $listRaw ? json_decode($listRaw, true) : [];

$users = [];
foreach ($idList as $uid) {
    $uRaw = $db->get($uid);
    if ($uRaw) {
        $u = json_decode($uRaw, true);
        unset($u['salt']);
        unset($u['hash']);
        $users[] = $u;
    }
}

jsonResponse(['success' => true, 'users' => $users]);
?>