<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$adminId = json_decode($sessionData, true)['userId'];
$adminUserRaw = $db->get($adminId);

$adminUser = $adminUserRaw ? json_decode($adminUserRaw, true) : [];
if (($adminUser['role'] ?? '') !== 'admin') {
    jsonError('无权访问', 403);
}

$cardId = $_GET['id'] ?? '';
$targetUserId = $_GET['userId'] ?? '';

if (!$cardId || !$targetUserId) jsonError('参数缺失');

$key = "bank_account_" . $targetUserId;
$accountRaw = $db->get($key);
if (!$accountRaw) jsonError('账户不存在');

$account = json_decode($accountRaw, true);
$cardFound = false;
$balanceDiff = 0;
$isBalanceChanged = false;
$logDescription = [];
$cardNumber = '';

$adminUsername = $adminUser['username'] ?? '管理员';
$targetUserRaw = $db->get($targetUserId);
$targetUsername = $targetUserRaw ? json_decode($targetUserRaw, true)['username'] : $targetUserId;

foreach ($account['cards'] as &$c) {
    if ($c['id'] === $cardId) {

        if (isset($_GET['holderName'])) $c['holderName'] = $_GET['holderName'];

        if (isset($_GET['balance'])) {
            $newBalance = floatval($_GET['balance']);
            $oldBalance = floatval($c['balance']);
            if (abs($newBalance - $oldBalance) > 0.0001) {
                $balanceDiff = $newBalance - $oldBalance;
                $c['balance'] = $newBalance;
                $isBalanceChanged = true;
                $logDescription[] = "余额调整";
            }
        }

        if (isset($_GET['creditLimit'])) {
            $oldLimit = floatval($c['creditLimit'] ?? 0);
            $newLimit = floatval($_GET['creditLimit']);
            if (abs($newLimit - $oldLimit) > 0.0001) {
                $c['creditLimit'] = $newLimit;
                $logDescription[] = "额度调整({$oldLimit}->{$newLimit})";
            }
        }

        if (isset($_GET['status'])) $c['status'] = $_GET['status'];

        $cardNumber = $c['cardNumber'];
        $cardFound = true;
        break;
    }
}

if (!$cardFound) jsonError('卡片未找到');

$db->set($key, json_encode($account));

if (!empty($logDescription)) {
    $txId = 'ADJ' . time() . rand(100, 999);
    $descStr = implode(', ', $logDescription);

    $logData = [
        'id' => $txId,
        'userId' => $targetUserId,
        'username' => $targetUsername,
        'type' => 'admin_adjust',
        'amount' => $isBalanceChanged ? $balanceDiff : 0,
        'timestamp' => date('Y-m-d H:i:s'),
        'description' => "管理员({$adminUsername})操作: 卡号{$cardNumber} {$descStr}"
    ];

    $logsRaw = $db->get('bank_transactions');
    $logs = $logsRaw ? json_decode($logsRaw, true) : [];
    array_unshift($logs, $logData);
    $db->set('bank_transactions', json_encode(array_slice($logs, 0, 500)));
}

jsonResponse(['success' => true]);
?>