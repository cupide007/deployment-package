<?php
require_once '../common.php';
$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$permsRaw = $db->get('sys_admin_permissions');
$permissions = $permsRaw ? json_decode($permsRaw, true) : [];

jsonResponse(['success' => true, 'permissions' => $permissions]);
?>