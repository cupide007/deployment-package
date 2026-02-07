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

$key = "bank_account_" . $userId;
if ($db->get($key)) {
    jsonResponse(['success' => true, 'message' => '账户已存在']);
}

$account = [
    'id' => $userId,
    'balance' => 1000,
    'cards' => [],
    'createdAt' => date('c')
];

$db->set($key, json_encode($account));

jsonResponse(['success' => true, 'message' => '开户成功', 'account' => $account]);
?>