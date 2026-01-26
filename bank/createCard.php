<?php
ob_start();
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$session = json_decode($sessionData, true);
$userId = $session['userId'];

$userRaw = $db->get($userId);
$user = json_decode($userRaw ?: '[]', true);
$username = $user['username'] ?? '未知用户';

$cardType = $_GET['cardType'] ?? 'debit';
$holderName = trim($_GET['holderName'] ?? '') ?: $username;

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
$account['cards'][] = $newCard;

$db->set($key, json_encode($account));
$db->set('idx_card_' . $cardNumber, $userId);

ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'card' => $newCard], JSON_UNESCAPED_UNICODE);
exit;