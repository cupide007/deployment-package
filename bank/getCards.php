<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
$userId = json_decode($sessionData, true)['userId'];

$accountRaw = $db->get("bank_account_" . $userId);
$account = $accountRaw ? json_decode($accountRaw, true) : ['cards' => []];

jsonResponse(['success' => true, 'cards' => $account['cards'] ?? []]);
?>