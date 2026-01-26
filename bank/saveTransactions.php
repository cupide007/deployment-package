<?php
require_once '../common.php';
$db = new Database('retinbox-main');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}
$sessionId = $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];
$data = getJsonInput();
$transactions = $data['transactions'] ?? [];
$txKey = "bank_tx_" . $userId;
$db->set($txKey, json_encode($transactions));
jsonResponse(['success' => true, 'message' => '交易记录已同步']);
?>