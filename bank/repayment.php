<?php
require_once '../common.php';
$db = new Database('retinbox-main');
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];
$input = json_decode(file_get_contents('php://input'), true);
$creditCardId = $input['creditCardId'];
$debitCardId = $input['debitCardId'];
$amount = floatval($input['amount']);
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
if ($creditCard['balance'] > 0) $creditCard['balance'] = 0;
$db->set($accountKey, json_encode($account));
$txId = 'REP' . time();
$date = date('c');
$txKey = "bank_tx_" . $userId;
$txs = json_decode($db->get($txKey), true) ?? [];
$txs[] = [
    'id' => $txId . '_dr', 'cardId' => $debitCardId, 'type' => 'repayment',
    'amount' => -$amount, 'description' => '信用卡还款', 'timestamp' => $date, 'status' => 'completed'
];
$txs[] = [
    'id' => $txId . '_cr', 'cardId' => $creditCardId, 'type' => 'repayment',
    'amount' => $amount, 'description' => '信用卡还款入账', 'timestamp' => $date, 'status' => 'completed'
];
$db->set($txKey, json_encode($txs));
jsonResponse(['success' => true]);
?>