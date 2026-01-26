<?php
require_once '../common.php';
$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$data = getJsonInput();
$templates = $data['templates'] ?? [];
$db->set('sys_permission_templates', json_encode($templates));

jsonResponse(['success' => true]);
?>