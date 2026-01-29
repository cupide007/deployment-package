<?php
require_once '../common.php';
$db = new Database('retinbox-main');
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$currentUser = json_decode($sessionData, true);
$cardId = $_GET['id'] ?? '';
$targetUserId = $currentUser['userId'];
if (!$cardId) jsonError('参数缺失');
$key = "bank_account_" . $targetUserId;
$accountRaw = $db->get($key);
if (!$accountRaw) jsonError('账户不存在');
$account = json_decode($accountRaw, true);
$newCards = [];
$deleted = false;
foreach ($account['cards'] as $c) {
    if ($c['id'] === $cardId) {
        if (abs(floatval($c['balance'])) > 0.01) {
            jsonError('卡内仍有余额或欠款，请先清算后再注销');
        }
        $deleted = true;
        continue;
    }
    $newCards[] = $c;
}
if (!$deleted) jsonError('未找到指定的卡片');
$account['cards'] = $newCards;
$db->set($key, json_encode($account));
jsonResponse(['success' => true]);
?>