<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

$userRaw = $db->get($userId);
$username = $userRaw ? json_decode($userRaw, true)['username'] : $userId;

$creditCardId = $_GET['creditCardId'] ?? '';
$debitCardId = $_GET['debitCardId'] ?? '';
$amount = floatval($_GET['amount'] ?? 0);

if ($amount <= 0) jsonError('金额无效');

$accountKey = "bank_account_" . $userId;
$account = json_decode($db->get($accountKey), true);

$creditIndex = -1;
$debitIndex = -1;
foreach ($account['cards'] as $i => $c) {
    if ($c['id'] === $creditCardId) $creditIndex = $i;
    if ($c['id'] === $debitCardId) $debitIndex = $i;
}

if ($creditIndex === -1 || $debitIndex === -1) jsonError('卡片不存在');

$creditCard = &$account['cards'][$creditIndex];
$debitCard = &$account['cards'][$debitIndex];

if ($debitCard['balance'] < $amount) jsonError('还款账户余额不足');


$debitCard['balance'] -= $amount;
$creditCard['balance'] += $amount;


$db->set($accountKey, json_encode($account));

$txId = 'REP' . time();
$date = date('c');


$txKey = "bank_tx_" . $userId;
$txs = json_decode($db->get($txKey) ?: '[]', true);

$txs[] = [
    'id' => $txId . '_dr', 'cardId' => $debitCardId, 'type' => 'repayment',
    'amount' => -$amount, 'description' => '信用卡还款', 'timestamp' => $date, 'status' => 'completed'
];

$txs[] = [
    'id' => $txId . '_cr', 'cardId' => $creditCardId, 'type' => 'repayment',
    'amount' => $amount, 'description' => '信用卡还款入账', 'timestamp' => $date, 'status' => 'completed'
];

$db->set($txKey, json_encode($txs));

$sysLogData = [
    'id' => $txId,
    'userId' => $userId,
    'username' => $username,
    'type' => 'repayment',
    'amount' => $amount,
    'timestamp' => date('Y-m-d H:i:s'),
    'description' => "信用卡还款: 借记卡({$debitCard['cardNumber']}) -> 信用卡({$creditCard['cardNumber']})"
];

$sysLogsRaw = $db->get('bank_transactions');
$sysLogs = $sysLogsRaw ? json_decode($sysLogsRaw, true) : [];
array_unshift($sysLogs, $sysLogData);
$db->set('bank_transactions', json_encode(array_slice($sysLogs, 0, 500)));

jsonResponse(['success' => true]);
?>