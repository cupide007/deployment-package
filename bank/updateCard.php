<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$adminId = json_decode($sessionData, true)['userId'];
$adminUserRaw = $db->get($adminId);
$adminUsername = $adminUserRaw ? json_decode($adminUserRaw, true)['username'] : '管理员';

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

$targetUserRaw = $db->get($targetUserId);
$targetUsername = $targetUserRaw ? json_decode($targetUserRaw, true)['username'] : $targetUserId;

$account = json_decode($accountRaw, true);
$cardFound = false;
$balanceDiff = 0;
$isBalanceChanged = false;
$cardNumber = '';

foreach ($account['cards'] as &$c) {
    if ($c['id'] === $cardId) {
        $c['holderName'] = $_GET['holderName'] ?? $c['holderName'];

        $oldBalance = floatval($c['balance']);
        $newBalance = floatval($_GET['balance'] ?? 0);

        if (abs($newBalance - $oldBalance) > 0.0001) {
            $balanceDiff = $newBalance - $oldBalance;
            $c['balance'] = $newBalance;
            $isBalanceChanged = true;
        }

        $c['creditLimit'] = floatval($_GET['creditLimit'] ?? 0);
        $c['status'] = $_GET['status'] ?? 'active';

        $cardNumber = $c['cardNumber'];
        $cardFound = true;
        break;
    }
}

if (!$cardFound) jsonError('卡片未找到');

$db->set($key, json_encode($account));

if ($isBalanceChanged) {
    $txId = 'ADJ' . time() . rand(100, 999);

    $logData = [
        'id' => $txId,
        'userId' => $targetUserId,
        'username' => $targetUsername,
        'type' => 'admin_adjust',
        'amount' => $balanceDiff,
        'timestamp' => date('Y-m-d H:i:s'),
        'description' => "管理员({$adminUsername})强制调账: 卡号{$cardNumber} 余额调整"
    ];

    $logsRaw = $db->get('bank_transactions');
    $logs = $logsRaw ? json_decode($logsRaw, true) : [];
    array_unshift($logs, $logData);
    $db->set('bank_transactions', json_encode(array_slice($logs, 0, 500)));
}

jsonResponse(['success' => true]);
?>