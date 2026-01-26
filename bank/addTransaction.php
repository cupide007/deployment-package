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

$data = getJsonInput();
$amount = floatval($data['amount'] ?? 0);
$type = $data['type'] ?? 'expense';
$desc = $data['description'] ?? '交易';

$key = "bank_account_" . $userId;
$accountRaw = $db->get($key);
if (!$accountRaw) jsonError('账户不存在');

$account = json_decode($accountRaw, true);
$account['balance'] += ($type === 'income' ? $amount : -$amount);
$db->set($key, json_encode($account));

jsonResponse(['success' => true, 'balance' => $account['balance']]);
?>