<?php
ob_start();
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
$loginUserId = json_decode($sessionData, true)['userId'];

$loginUserRaw = $db->get($loginUserId);
$isAdmin = ($loginUserRaw && (json_decode($loginUserRaw, true)['role'] ?? '') === 'admin');

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];

$fromCardId = $input['fromCardId'] ?? $_GET['fromCardId'] ?? $_GET['cardId'] ?? '';
$toCardNumber = $input['toCardNumber'] ?? $_GET['toCardNumber'] ?? '';
$targetUserId = $input['targetUserId'] ?? $_GET['targetUserId'] ?? '';
$amount = floatval($input['amount'] ?? $_GET['amount'] ?? 0);
$description = $input['description'] ?? $_GET['description'] ?? '转账';
$payerUserIdInput = $input['userId'] ?? $_GET['userId'] ?? '';

if ($amount <= 0) jsonError('金额必须大于0');

$senderId = $loginUserId;
if ($isAdmin && $payerUserIdInput) {
    $senderId = $payerUserIdInput;
}

$senderUserRaw = $db->get($senderId);
$senderUsername = $senderUserRaw ? json_decode($senderUserRaw, true)['username'] : $senderId;

$senderKey = "bank_account_" . $senderId;
$senderAccountRaw = $db->get($senderKey);
if (!$senderAccountRaw) jsonError('付款人账户不存在');
$senderAccount = json_decode($senderAccountRaw, true);

$senderCardIndex = -1;
foreach ($senderAccount['cards'] as $index => $card) {
    if ($card['id'] === $fromCardId) {
        $senderCardIndex = $index;
        break;
    }
}
if ($senderCardIndex === -1) jsonError('付款卡片未找到');
$senderCard = &$senderAccount['cards'][$senderCardIndex];

$totalDeduction = $amount;
if ($senderCard['cardType'] === 'credit') {
    $totalDeduction = $amount * 1.01;
}

if ($senderCard['cardType'] === 'debit') {
    if ($senderCard['balance'] < $totalDeduction) jsonError('余额不足');
} else {
    $currentDebt = ($senderCard['balance'] < 0) ? abs($senderCard['balance']) : 0;
    if (($senderCard['creditLimit'] - $currentDebt) < $totalDeduction) jsonError('信用额度不足');
}

$recipientId = '';
$recipientCardIndex = -1;

if ($toCardNumber) {
    $recipientId = $db->get('idx_card_' . $toCardNumber);
} elseif ($targetUserId) {
    $recipientId = $targetUserId;
}

if (!$recipientId) jsonError('收款账户不存在或未指定');

$recipientKey = "bank_account_" . $recipientId;

if ($recipientId === $senderId) {
    $recipientAccount =& $senderAccount;
} else {
    $recipientAccount = json_decode($db->get($recipientKey), true);
    if (!$recipientAccount) jsonError('收款账户数据异常');
}

if ($toCardNumber) {
    foreach ($recipientAccount['cards'] as $index => $card) {
        if ($card['cardNumber'] === $toCardNumber) {
            $recipientCardIndex = $index;
            break;
        }
    }
} else {
    foreach ($recipientAccount['cards'] as $index => $card) {
        if (($card['status'] ?? 'active') === 'active') {
            $recipientCardIndex = $index;
            if ($card['cardType'] === 'debit') break;
        }
    }
}

if ($recipientCardIndex === -1) jsonError('收款人没有可用的银行卡');
$recipientCard = &$recipientAccount['cards'][$recipientCardIndex];
$toCardNumber = $recipientCard['cardNumber'];

$senderCard['balance'] -= $totalDeduction;
$recipientCard['balance'] += $amount;

$db->set($senderKey, json_encode($senderAccount));

if ($senderId !== $recipientId) {
    $db->set($recipientKey, json_encode($recipientAccount));
}

$txId = 'TRX' . time() . rand(100, 999);
$date = date('c');

$txData = [
    'id' => $txId, 'type' => 'transfer', 'amount' => -$totalDeduction,
    'description' => "转给 {$toCardNumber}: " . $description, 'timestamp' => $date
];
$senderTxKey = "bank_tx_" . $senderId;
$senderTxs = json_decode($db->get($senderTxKey) ?: '[]', true);
array_unshift($senderTxs, $txData);
$db->set($senderTxKey, json_encode($senderTxs));

$recTxData = [
    'id' => $txId . '_R', 'type' => 'transfer', 'amount' => $amount,
    'description' => "收到 {$senderUsername} 转账: " . $description, 'timestamp' => $date
];
$recipientTxKey = "bank_tx_" . $recipientId;

if ($senderId === $recipientId) {
    $recipientTxs = json_decode($db->get($senderTxKey) ?: '[]', true);
} else {
    $recipientTxs = json_decode($db->get($recipientTxKey) ?: '[]', true);
}
array_unshift($recipientTxs, $recTxData);
$db->set($recipientTxKey, json_encode($recipientTxs));

$globalLogData = [
    'id' => $txId,
    'userId' => $senderId,
    'username' => $senderUsername,
    'type' => 'transfer',
    'amount' => $amount,
    'timestamp' => date('Y-m-d H:i:s'),
    'description' => ($isAdmin ? "[管理员操作] " : "") . "用户转账: {$senderUsername} -> {$toCardNumber} ({$description})"
];
$globalLogsRaw = $db->get('bank_transactions');
$globalLogs = $globalLogsRaw ? json_decode($globalLogsRaw, true) : [];
array_unshift($globalLogs, $globalLogData);
$db->set('bank_transactions', json_encode(array_slice($globalLogs, 0, 500)));

ob_clean();
jsonResponse(['success' => true]);
?>