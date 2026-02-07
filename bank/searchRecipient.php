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
        $username = $u['username'] ?? '';

        $acc = json_decode($db->get('bank_account_' . $uid) ?: '{}', true);
        $cards = $acc['cards'] ?? [];

        $isMatch = false;

        if (stripos($username, $keyword) !== false) {
            $isMatch = true;
        }
        else {
            foreach ($cards as $card) {
                if (isset($card['holderName']) && stripos($card['holderName'], $keyword) !== false) {
                    $isMatch = true;
                    break;
                }
            }
        }

        if ($isMatch) {
            foreach ($cards as $card) {
                if (($card['status'] ?? 'active') !== 'active') continue;

                $results[] = [
                    'cardNumber' => $card['cardNumber'],
                    'holderName' => $card['holderName'] ?? $username,
                    'cardType'   => $card['cardType'],
                    'userId'     => $uid
                ];
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