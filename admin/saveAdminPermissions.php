<?php
require_once '../common.php';
$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$data = getJsonInput();
$permissions = $data['permissions'] ?? [];
$db->set('sys_admin_permissions', json_encode($permissions));

jsonResponse(['success' => true]);
?>