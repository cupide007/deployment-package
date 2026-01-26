<?php
require_once '../common.php';
$db = new Database('retinbox-main');
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$senderId = json_decode($sessionData, true)['userId'];
$input = json_decode(file_get_contents('php://input'), true);
$fromCardId = $input['fromCardId'];
$toCardNumber = $input['toCardNumber'];
$amount = floatval($input['amount']);
$description = $input['description'] ?? '转账';
if ($amount <= 0) jsonError('金额必须大于0');
$senderKey = "bank_account_" . $senderId;
$senderAccount = json_decode($db->get($senderKey), true);
if (!$senderAccount) jsonError('付款账户不存在');
$senderCardIndex = -1;
foreach ($senderAccount['cards'] as $index => $card) {
    if ($card['id'] === $fromCardId) {
        $senderCardIndex = $index;
        break;
    }
}
if ($senderCardIndex === -1) jsonError('付款卡片未找到');
$senderCard = &$senderAccount['cards'][$senderCardIndex];
if ($senderCard['cardType'] === 'debit') {
    if ($senderCard['balance'] < $amount) jsonError('余额不足');
} else {
    $debt = ($senderCard['balance'] < 0) ? abs($senderCard['balance']) : 0;
    if (($senderCard['creditLimit'] - $debt) < $amount) jsonError('信用卡额度不足');
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
if ($recipientCardIndex === -1) jsonError('收款卡片数据异常');
$recipientCard = &$recipientAccount['cards'][$recipientCardIndex];
if ($senderCard['cardType'] === 'credit') {
    $senderCard['balance'] -= ($amount * 1.01);
} else {
    $senderCard['balance'] -= $amount;
}
$recipientCard['balance'] += $amount;
$db->set($senderKey, json_encode($senderAccount));
if ($senderId !== $recipientId) {
    $db->set($recipientKey, json_encode($recipientAccount));
}
$txId = 'TRX' . time() . rand(100,999);
$date = date('c');
$senderTx = [
    'id' => $txId, 'cardId' => $fromCardId, 'type' => 'transfer',
    'amount' => ($senderCard['cardType'] === 'credit') ? -($amount * 1.01) : -$amount,
    'description' => $description,
    'recipient' => ['name' => $recipientCard['holderName'], 'account' => $toCardNumber],
    'timestamp' => $date, 'status' => 'completed'
];
$senderTxKey = "bank_tx_" . $senderId;
$senderTxs = json_decode($db->get($senderTxKey), true) ?? [];
array_unshift($senderTxs, $senderTx);
$db->set($senderTxKey, json_encode($senderTxs));
$recipientTx = [
    'id' => $txId . '_in', 'cardId' => $recipientCard['id'], 'type' => 'transfer',
    'amount' => $amount,
    'description' => "收到转账: " . $description,
    'sender' => ['name' => $senderCard['holderName'], 'account' => $senderCard['cardNumber']],
    'timestamp' => $date, 'status' => 'completed'
];
$recipientTxKey = "bank_tx_" . $recipientId;
$recipientTxs = json_decode($db->get($recipientTxKey), true) ?? [];
array_unshift($recipientTxs, $recipientTx);
$db->set($recipientTxKey, json_encode($recipientTxs));
jsonResponse(['success' => true]);
?>