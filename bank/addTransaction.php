<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('请求方法不允许', 405);
}

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

$userRaw = $db->get($userId);
$username = $userRaw ? json_decode($userRaw, true)['username'] : $userId;

$data = getJsonInput();
$cardId = $data['cardId'] ?? '';
$amount = floatval($data['amount'] ?? 0);
$type = $data['type'] ?? 'expense';
$desc = $data['description'] ?? '交易';

if (!$cardId) jsonError('未指定银行卡');
if ($amount <= 0) jsonError('金额必须大于0');

$key = "bank_account_" . $userId;
$accountRaw = $db->get($key);
if (!$accountRaw) jsonError('账户不存在');

$account = json_decode($accountRaw, true);
$cardFound = false;
$newBalance = 0;
$cardNumber = '';

foreach ($account['cards'] as &$card) {
    if ($card['id'] === $cardId) {
        $realAmount = ($type === 'income') ? $amount : -$amount;

        if ($type === 'expense') {
            if ($card['cardType'] === 'debit' && $card['balance'] < $amount) {
                jsonError('余额不足');
            }
        }

        $card['balance'] += $realAmount;
        $newBalance = $card['balance'];
        $cardNumber = $card['cardNumber'];
        $cardFound = true;
        break;
    }
}

if (!$cardFound) jsonError('卡片未找到');

$db->set($key, json_encode($account));

$txId = 'TX' . time() . rand(100, 999);
$date = date('c');
$txKey = "bank_tx_" . $userId;
$txs = json_decode($db->get($txKey) ?: '[]', true);
$txs[] = [
    'id' => $txId,
    'cardId' => $cardId,
    'type' => $type,
    'amount' => ($type === 'income' ? $amount : -$amount),
    'description' => $desc,
    'timestamp' => $date
];
$db->set($txKey, json_encode($txs));

$sysLogData = [
    'id' => $txId,
    'userId' => $userId,
    'username' => $username,
    'type' => $type,
    'amount' => ($type === 'income' ? $amount : -$amount),
    'timestamp' => date('Y-m-d H:i:s'),
    'description' => "API交易: {$cardNumber} ({$desc})"
];
$sysLogsRaw = $db->get('bank_transactions');
$sysLogs = $sysLogsRaw ? json_decode($sysLogsRaw, true) : [];
array_unshift($sysLogs, $sysLogData);
$db->set('bank_transactions', json_encode(array_slice($sysLogs, 0, 500)));

jsonResponse(['success' => true, 'balance' => $newBalance]);
?>