<?php
ob_start();

require_once '../common.php';
$db = new Database('retinbox-main');

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

if ($keyword === '') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (is_array($jsonInput) && isset($jsonInput['keyword'])) {
        $keyword = trim($jsonInput['keyword']);
    }
}

$results = [];

if ($keyword !== '') {

    if (is_numeric($keyword)) {
        $targetUserId = $db->get('idx_card_' . $keyword);
        if ($targetUserId) {
            $acc = json_decode($db->get('bank_account_' . $targetUserId)?:'{}', true);
            if (isset($acc['cards'])) {
                foreach ($acc['cards'] as $card) {
                    if ($card['cardNumber'] === $keyword) {
                        $results[] = [
                            'cardNumber' => $card['cardNumber'],
                            'holderName' => $card['holderName'] ?? '用户',
                            'cardType'   => $card['cardType'],
                            'userId'     => $targetUserId
                        ];
                    }
                }
            }
        }
    }

    $list1 = json_decode($db->get('sys_users_list') ?: '[]', true);
    $list2 = json_decode($db->get('users_list') ?: '[]', true);
    $allUserIds = array_unique(array_merge($list1, $list2));

    foreach ($allUserIds as $uid) {
        $userRaw = $db->get($uid);
        if (!$userRaw) continue;
        $u = json_decode($userRaw, true);

        if (isset($u['username']) && stripos($u['username'], $keyword) !== false) {
            $acc = json_decode($db->get('bank_account_' . $u['id']) ?: '{}', true);
            if (isset($acc['cards'])) {
                foreach ($acc['cards'] as $card) {
                    $results[] = [
                        'cardNumber' => $card['cardNumber'],
                        'holderName' => $card['holderName'] ?? $u['username'],
                        'cardType'   => $card['cardType'],
                        'userId'     => $u['id']
                    ];
                }
            }
        }
    }
}

$uniqueResults = [];
$seenCards = [];
foreach ($results as $r) {
    if (!in_array($r['cardNumber'], $seenCards)) {
        $seenCards[] = $r['cardNumber'];
        $uniqueResults[] = $r;
    }
}

ob_clean();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'results' => $uniqueResults], JSON_UNESCAPED_UNICODE);
exit;
?>