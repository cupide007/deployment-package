<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);

$userId = $_GET['id'] ?? '';
if (!$userId) jsonError('缺少用户ID');

$userRaw = $db->get($userId);
if (!$userRaw) jsonError('用户不存在');

$user = json_decode($userRaw, true);

if (isset($user['username'])) {
    $db->delete("idx_username_" . md5($user['username']));
}


$db->delete($userId);

$db->delete("bank_account_" . $userId);

jsonResponse(['success' => true, 'message' => '用户删除成功']);
?>