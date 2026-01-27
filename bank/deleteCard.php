<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$cardId = $_GET['id'] ?? '';
$targetUserId = $_GET['userId'] ?? '';

if (!$cardId || !$targetUserId) jsonError('参数缺失');

$key = "bank_account_" . $targetUserId;
$accountRaw = $db->get($key);
if (!$accountRaw) jsonError('账户不存在');

$account = json_decode($accountRaw, true);
$account['cards'] = array_filter($account['cards'], function($c) use ($cardId) {
    return $c['id'] !== $cardId;
});
$account['cards'] = array_values($account['cards']);

$db->set($key, json_encode($account));
jsonResponse(['success' => true]);