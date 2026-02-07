<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 bank 模块权限
requireModulePermission($db, 'bank');

$page = intval($_GET['page'] ?? 1);
$pageSize = intval($_GET['pageSize'] ?? 5);

$usersList = json_decode($db->get('sys_users_list') ?: '[]', true);
$allCards = [];
$usersForBank = []; // 用于银行操作的用户列表

foreach ($usersList as $uid) {
    $uRaw = $db->get($uid);
    $uInfo = $uRaw ? json_decode($uRaw, true) : null;
    
    // 收集用户基本信息（用于银行操作下拉选择）
    if ($uInfo) {
        $usersForBank[] = [
            'id' => $uid,
            'username' => $uInfo['username'] ?? '未知'
        ];
    }
    
    $accRaw = $db->get("bank_account_" . $uid);
    if ($accRaw) {
        $acc = json_decode($accRaw, true);
        $cards = $acc['cards'] ?? [];
        foreach ($cards as &$c) {
            $c['username'] = $uInfo['username'] ?? '未知';
            $c['userId'] = $uid;
        }
        $allCards = array_merge($allCards, $cards);
    }
}

$total = count($allCards);
$offset = ($page - 1) * $pageSize;
$pagedCards = array_slice($allCards, $offset, $pageSize);

jsonResponse([
    'success' => true,
    'cards' => $pagedCards,
    'total' => $total,
    'page' => $page,
    'pageSize' => $pageSize,
    'users' => $usersForBank // 用于银行模块的用户选择列表
]);