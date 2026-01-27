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
foreach ($account['cards'] as &$c) {
    if ($c['id'] === $cardId) {
        $c['holderName'] = $_GET['holderName'] ?? $c['holderName'];
        $c['balance'] = floatval($_GET['balance'] ?? 0);
        $c['creditLimit'] = floatval($_GET['creditLimit'] ?? 0);
        $c['status'] = $_GET['status'] ?? 'active';
        break;
    }
}

$db->set($key, json_encode($account));
jsonResponse(['success' => true]);
?>