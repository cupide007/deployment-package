<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

$data = getJsonInput();
$amount = floatval($data['amount'] ?? 0);

$key = "bank_account_" . $userId;
$accountRaw = $db->get($key);
if (!$accountRaw) jsonError('账户不存在');

$account = json_decode($accountRaw, true);
$account['balance'] += $amount;
$db->set($key, json_encode($account));

$txKey = "bank_tx_" . $userId;
$txListRaw = $db->get($txKey);
$transactions = $txListRaw ? json_decode($txListRaw, true) : [];
$newTx = [
    'id' => uniqid('tx_'),
    'amount' => abs($amount),
    'type' => $amount > 0 ? 'income' : 'expense',
    'description' => $data['description'] ?? '余额调整',
    'date' => date('c'),
    'balance_after' => $account['balance']
];
array_unshift($transactions, $newTx);
$db->set($txKey, json_encode($transactions));

jsonResponse(['success' => true, 'balance' => $account['balance']]);
?>