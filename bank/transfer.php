<?php
ob_start();
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$fromCardId = $input['fromCardId'] ?? $_GET['fromCardId'] ?? '';
$toCardNumber = $input['toCardNumber'] ?? $_GET['toCardNumber'] ?? '';
$amount = floatval($input['amount'] ?? $_GET['amount'] ?? 0);
$description = $input['description'] ?? $_GET['description'] ?? '转账';

if ($amount <= 0) jsonError('金额必须大于0');

$sessionData = $db->get('sess_' . $sessionId);
$senderId = json_decode($sessionData, true)['userId'];

$senderKey = "bank_account_" . $senderId;
$senderAccount = json_decode($db->get($senderKey), true);
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

$recipientId = $db->get('idx_card_' . $toCardNumber);
if (!$recipientId) jsonError('收款账户不存在');

$recipientKey = "bank_account_" . $recipientId;
$recipientAccount = ($recipientId === $senderId) ? $senderAccount : json_decode($db->get($recipientKey), true);
$recipientCardIndex = -1;
foreach ($recipientAccount['cards'] as $index => $card) {
    if ($card['cardNumber'] === $toCardNumber) {
        $recipientCardIndex = $index;
        break;
    }
}
if ($recipientCardIndex === -1) jsonError('收款卡片异常');
$recipientCard = &$recipientAccount['cards'][$recipientCardIndex];

$senderCard['balance'] -= $totalDeduction;
$recipientCard['balance'] += $amount;

$db->set($senderKey, json_encode($senderAccount));
if ($senderId !== $recipientId) {
    $db->set($recipientKey, json_encode($recipientAccount));
}

$txId = 'TRX' . time();
$date = date('c');
$txData = [
    'id' => $txId, 'type' => 'transfer', 'amount' => -$totalDeduction,
    'description' => $description, 'timestamp' => $date
];
$senderTxKey = "bank_tx_" . $senderId;
$senderTxs = json_decode($db->get($senderTxKey) ?: '[]', true);
array_unshift($senderTxs, $txData);
$db->set($senderTxKey, json_encode($senderTxs));

$recTxData = [
    'id' => $txId . '_R', 'type' => 'transfer', 'amount' => $amount,
    'description' => "收到转账: " . $description, 'timestamp' => $date
];
$recipientTxKey = "bank_tx_" . $recipientId;
$recipientTxs = json_decode($db->get($recipientTxKey) ?: '[]', true);
array_unshift($recipientTxs, $recTxData);
$db->set($recipientTxKey, json_encode($recipientTxs));

ob_clean();
jsonResponse(['success' => true]);
?>