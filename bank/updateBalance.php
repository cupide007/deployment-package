<?php
require_once '../common.php';
$db = new Database('retinbox-main');

// 需要 bank 模块权限
requireModulePermission($db, 'bank');

$cardId = $_GET['cardId'] ?? '';
$userId = $_GET['userId'] ?? '';
$amount = floatval($_GET['amount'] ?? 0);
$action = $_GET['action'] ?? 'update';

if (!$userId || !$cardId) {
    echo json_encode(['success' => false, 'error' => '参数缺失 (需要 userId 和 cardId)']);
    exit;
}

$key = "bank_account_" . $userId;
$accountRaw = $db->get($key);

if (!$accountRaw) {
    echo json_encode(['success' => false, 'error' => '未找到该用户的账户数据']);
    exit;
}

$account = json_decode($accountRaw, true);

$cardFound = false;
$newBalance = 0;
$cardIndex = -1;

if (isset($account['cards']) && is_array($account['cards'])) {
    foreach ($account['cards'] as $index => $card) {
        if ($card['id'] === $cardId) {
            $cardIndex = $index;
            $currentBalance = floatval($card['balance'] ?? 0);

            if ($action === 'deposit') {
                $currentBalance += $amount;
            } elseif ($action === 'withdraw') {
                if ($currentBalance >= $amount) {
                    $currentBalance -= $amount;
                } else {
                    echo json_encode(['success' => false, 'error' => '余额不足']);
                    exit;
                }
            } else {
                $currentBalance = $amount;
            }

            $account['cards'][$index]['balance'] = $currentBalance;
            $newBalance = $currentBalance;
            $cardFound = true;
            break;
        }
    }
}

if (!$cardFound) {
    echo json_encode(['success' => false, 'error' => '未找到指定的银行卡']);
    exit;
}

$db->set($key, json_encode($account));

$logData = [
    'id' => 'TX' . time() . rand(100, 999),
    'userId' => $userId,
    'username' => $userId,
    'cardId' => $cardId,
    'type' => $action,
    'amount' => ($action === 'withdraw' ? -$amount : $amount),
    'timestamp' => date('Y-m-d H:i:s'),
    'description' => ($action === 'deposit' ? '管理员存款' : ($action === 'withdraw' ? '管理员取款' : '管理员调账'))
];

$logsRaw = $db->get('bank_transactions');
$logs = $logsRaw ? json_decode($logsRaw, true) : [];
array_unshift($logs, $logData);
$db->set('bank_transactions', json_encode(array_slice($logs, 0, 500)));

echo json_encode(['success' => true, 'newBalance' => $newBalance]);
?>