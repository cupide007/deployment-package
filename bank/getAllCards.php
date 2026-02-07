<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 bank 模块权限
requireModulePermission($db, 'bank');

$page = intval($_GET['page'] ?? 1);
$pageSize = intval($_GET['pageSize'] ?? 5);

$usersList = json_decode($db->get('sys_users_list') ?: '[]', true);
$allCards = [];

foreach ($usersList as $uid) {
    $accRaw = $db->get("bank_account_" . $uid);
    if ($accRaw) {
        $acc = json_decode($accRaw, true);
        $cards = $acc['cards'] ?? [];
        $uRaw = $db->get($uid);
        $uInfo = json_decode($uRaw, true);
        foreach ($cards as &$c) {
            $c['username'] = $uInfo['username'] ?? '未知';
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
    'pageSize' => $pageSize
]);