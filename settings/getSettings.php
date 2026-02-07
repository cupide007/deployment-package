<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

$key = "user_settings_" . $userId;
$settingsRaw = $db->get($key);
$settings = $settingsRaw ? json_decode($settingsRaw, true) : new stdClass();

jsonResponse(['success' => true, 'settings' => $settings]);
?>