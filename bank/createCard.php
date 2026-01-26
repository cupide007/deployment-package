<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$session = json_decode($sessionData, true);
$userId = $session['userId'];

$userRaw = $db->get($userId);
if (!$userRaw) jsonError('用户数据不存在', 401);
$user = json_decode($userRaw, true);
$username = $user['username'];

$input = json_decode(file_get_contents('php://input'), true);
$cardType = $input['cardType'] ?? 'debit';
$inputHolder = trim($input['holderName'] ?? '');
$holderName = $inputHolder ?: $username;

do {
    $cardNumber = '4' . rand(100, 999) . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
    $exists = $db->get('idx_card_' . $cardNumber);
} while ($exists);

$newCard = [
    'id' => uniqid('card_'),
    'userId' => $userId,
    'cardNumber' => $cardNumber,
    'cardType' => $cardType,
    'balance' => 0,
    'creditLimit' => ($cardType === 'credit') ? 5000 : 0,
    'holderName' => $holderName,
    'createdAt' => date('c')
];

$key = "bank_account_" . $userId;
$accountRaw = $db->get($key);
$account = $accountRaw ? json_decode($accountRaw, true) : ['cards' => []];

if (!isset($account['cards'])) $account['cards'] = [];
$account['cards'][] = $newCard;

$db->set($key, json_encode($account));

$db->set('idx_card_' . $cardNumber, $userId);

jsonResponse(['success' => true, 'card' => $newCard]);
?>