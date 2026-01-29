<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$targetUserId = $_GET['userId'] ?? '';
$targetName = trim($_GET['name'] ?? '');

if (!$targetUserId && !$targetName) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'cards' => []]);
    exit;
}

$cards = [];
$targetUserIds = [];

if ($targetUserId) {
    $targetUserIds[] = $targetUserId;
} else {
    $usersList = json_decode($db->get('sys_users_list') ?: '[]', true);
    $list2 = json_decode($db->get('users_list') ?: '[]', true);
    $allUserIds = array_unique(array_merge($usersList, $list2));

    foreach ($allUserIds as $uid) {
        $userRaw = $db->get($uid);
        if (!$userRaw) continue;
        $user = json_decode($userRaw, true);

        if (($user['username'] ?? '') === $targetName) {
            $targetUserIds[] = $uid;
        } else {
            $accountRaw = $db->get("bank_account_" . $uid);
            $account = $accountRaw ? json_decode($accountRaw, true) : ['cards' => []];
            if (isset($account['cards'])) {
                foreach ($account['cards'] as $card) {
                    if (($card['holderName'] ?? '') === $targetName) {
                        $targetUserIds[] = $uid;
                        break;
                    }
                }
            }
        }
    }
}

$targetUserIds = array_unique($targetUserIds);

foreach ($targetUserIds as $uid) {
    $accountRaw = $db->get("bank_account_" . $uid);
    $account = $accountRaw ? json_decode($accountRaw, true) : ['cards' => []];

    if (isset($account['cards'])) {
        foreach ($account['cards'] as $card) {
            if (($card['status'] ?? 'active') === 'active') {
                $cards[] = [
                    'cardNumber' => $card['cardNumber'],
                    'holderName' => $card['holderName'],
                    'cardType' => $card['cardType'],
                    'userId' => $uid
                ];
            }
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'cards' => $cards], JSON_UNESCAPED_UNICODE);
?>