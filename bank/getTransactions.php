<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$sessionUser = json_decode($sessionData, true);
$userId = $sessionUser['userId'];

$userRaw = $db->get($userId);
$user = $userRaw ? json_decode($userRaw, true) : null;

if ($user && isset($user['role']) && $user['role'] === 'admin') {
    $listRaw = $db->get('bank_transactions');
} else {
    $key = "bank_tx_" . $userId;
    $listRaw = $db->get($key);
}

$list = $listRaw ? json_decode($listRaw, true) : [];

jsonResponse(['success' => true, 'transactions' => $list]);
?>