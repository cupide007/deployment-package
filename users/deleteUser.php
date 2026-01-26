<?php
require_once '../common.php';
$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);

$data = getJsonInput();
$targetId = $data['id'] ?? '';
if (!$targetId) jsonError('缺少ID');

$userRaw = $db->get($targetId);
if ($userRaw) {
    $u = json_decode($userRaw, true);
    $db->delete("idx_username_" . md5($u['username']));
    $db->delete("idx_email_" . md5($u['email']));
}
$db->delete($targetId);
$db->delete("bank_account_" . $targetId);

$listRaw = $db->get('sys_users_list');
$list = $listRaw ? json_decode($listRaw, true) : [];
$newList = array_values(array_diff($list, [$targetId]));
$db->set('sys_users_list', json_encode($newList));

jsonResponse(['success' => true, 'message' => '已删除']);
?>