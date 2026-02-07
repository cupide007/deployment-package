<?php
ob_start();
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$currentUser = json_decode($sessionData, true);
$currentUserId = $currentUser['userId'];

// 获取当前用户信息以检查权限
$currentUserRaw = $db->get($currentUserId);
$currentUserData = $currentUserRaw ? json_decode($currentUserRaw, true) : [];
$isAdmin = ($currentUserData['role'] ?? '') === 'admin';

$targetUserId = $_GET['userId'] ?? $currentUserId;

// 如果目标用户不是自己，必须是管理员
if ($targetUserId !== $currentUserId && !$isAdmin) {
    jsonError('无权为其他用户创建银行卡', 403);
}

if (!$targetUserId) jsonError('未指定用户ID');

$userRaw = $db->get($targetUserId);
if (!$userRaw) jsonError('目标用户不存在');
$user = json_decode($userRaw, true);

$cardType = $_GET['cardType'] ?? 'debit';
$holderName = trim($_GET['holderName'] ?? '') ?: ($user['username'] ?? '未知用户');

if ($cardType === 'credit') {
    $initialBalance = 0;
    $creditLimit = floatval($_GET['creditLimit'] ?? 5000);
} else {
    $initialBalance = floatval($_GET['balance'] ?? 0);
    $creditLimit = 0;
}

do {
    $cardNumber = '4' . rand(100, 999) . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
    $exists = $db->get('idx_card_' . $cardNumber);
} while ($exists);

$newCard = [
    'id' => uniqid('card_'),
    'userId' => $targetUserId,
    'cardNumber' => $cardNumber,
    'cardType' => $cardType,
    'balance' => $initialBalance,
    'creditLimit' => $creditLimit,
    'holderName' => $holderName,
    'status' => (isset($_GET['status']) && !empty($_GET['status'])) ? $_GET['status'] : 'active',
    'createdAt' => date('Y-m-d H:i:s')
];

$key = "bank_account_" . $targetUserId;
$accountRaw = $db->get($key);
$account = $accountRaw ? json_decode($accountRaw, true) : ['cards' => []];
$account['cards'][] = $newCard;

$db->set($key, json_encode($account));
$db->set('idx_card_' . $cardNumber, $targetUserId);

ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'card' => $newCard], JSON_UNESCAPED_UNICODE);
exit;
?>