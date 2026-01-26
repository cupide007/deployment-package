<?php
require_once '../common.php';
$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
$userId = json_decode($sessionData, true)['userId'];

$data = getJsonInput();
$cards = $data['cards'] ?? [];

$key = "bank_account_" . $userId;
$accountRaw = $db->get($key);
$account = $accountRaw ? json_decode($accountRaw, true) : ['balance' => 0];
$account['cards'] = $cards;
$db->set($key, json_encode($account));

jsonResponse(['success' => true]);
?>