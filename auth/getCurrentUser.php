<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) {
    jsonResponse(['success' => true, 'user' => null]);
}

$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) {
    jsonResponse(['success' => true, 'user' => null]);
}

$session = json_decode($sessionData, true);
$userId = $session['userId'];

$userRaw = $db->get($userId);
if (!$userRaw) {
    jsonResponse(['success' => true, 'user' => null]);
}

$user = json_decode($userRaw, true);
$user['id'] = $userId; // 确保返回用户ID
unset($user['salt']);
unset($user['hash']);

$session['lastActive'] = time();
$db->set('sess_' . $sessionId, json_encode($session));

jsonResponse(['success' => true, 'user' => $user]);
?>