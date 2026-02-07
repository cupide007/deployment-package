<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$userId = $_GET['userId'] ?? '';
if (!$userId) jsonError('缺少用户ID');

$userRaw = $db->get($userId);
if (!$userRaw) jsonError('用户不存在', 404);

$user = json_decode($userRaw, true);
unset($user['salt']);
unset($user['hash']);
unset($user['email']);

jsonResponse(['success' => true, 'user' => $user]);
?>