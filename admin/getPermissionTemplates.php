<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$tempsRaw = $db->get('sys_permission_templates');
$templates = $tempsRaw ? json_decode($tempsRaw, true) : [];

jsonResponse(['success' => true, 'templates' => $templates]);
?>