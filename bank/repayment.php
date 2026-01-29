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
if ($creditCardId === $debitCardId) jsonError('无法使用同一张卡进行还款');

$accountKey = "bank_account_" . $userId;
$accountRaw = $db->get($accountKey);
if (!$accountRaw) jsonError('账户不存在');
$account = json_decode($accountRaw, true);

$creditIndex = -1;
$debitIndex = -1;

foreach ($account['cards'] as $i => $c) {
    if ($c['id'] === $creditCardId) $creditIndex = $i;
    if ($c['id'] === $debitCardId) $debitIndex = $i;
}

if ($creditIndex === -1) jsonError('信用卡不存在');
if ($debitIndex === -1) jsonError('付款借记卡不存在');

$creditCard = &$account['cards'][$creditIndex];
$debitCard = &$account['cards'][$debitIndex];

if (($creditCard['cardType'] ?? '') !== 'credit') jsonError('目标卡片不是信用卡');
if (($debitCard['cardType'] ?? '') !== 'debit') jsonError('支付账户必须是借记卡');

if ($debitCard['balance'] < $amount) jsonError('还款账户余额不足');

$debitCard['balance'] -= $amount;
$creditCard['balance'] += $amount;

$db->set($accountKey, json_encode($account));

$txId = 'REP' . time() . rand(100, 999);
$date = date('c');

$txKey = "bank_tx_" . $userId;
$txs = json_decode($db->get($txKey) ?: '[]', true);

$txs[] = [
    'id' => $txId . '_dr',
    'cardId' => $debitCardId,
    'type' => 'repayment',
    'amount' => -$amount,
    'description' => '信用卡还款支出',
    'timestamp' => $date,
    'status' => 'completed'
];

$txs[] = [
    'id' => $txId . '_cr',
    'cardId' => $creditCardId,
    'type' => 'repayment',
    'amount' => $amount,
    'description' => '信用卡还款入账',
    'timestamp' => $date,
    'status' => 'completed'
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