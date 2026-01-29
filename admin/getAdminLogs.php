<?php
\RthRunner\Runtime::require_once_method('../common.php');
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$logsRaw = $db->get('sys_admin_logs');
$logs = $logsRaw ? json_decode($logsRaw, true) : [];

jsonResponse(['success' => true, 'logs' => $logs]);
?>