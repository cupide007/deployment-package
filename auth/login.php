<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('请求方法不允许', 405);
}

$data = getJsonInput();
$account = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$account || !$password) {
    jsonError('账号和密码不能为空');
}

$targetId = $db->get("idx_email_" . md5($account));
if (!$targetId) {
    $targetId = $db->get("idx_username_" . md5($account));
}

if (!$targetId) {
    jsonError('账号不存在或密码错误');
}

$userDataStr = $db->get($targetId);
if (!$userDataStr) {
    jsonError('用户数据异常');
}

$user = json_decode($userDataStr, true);

$verifyHash = hash_pbkdf2('sha512', $password, $user['salt'], 1000, 64);
if ($verifyHash !== $user['hash']) {
    jsonError('账号不存在或密码错误');
}

$user['lastLogin'] = date('c');
$db->set($targetId, json_encode($user));

$sessionId = bin2hex(random_bytes(32));
$sessionData = [
    'userId' => $user['id'],
    'loginTime' => time()
];
$db->set('sess_' . $sessionId, json_encode($sessionData));

setcookie('sessionId', $sessionId, time() + 86400 * 7, '/', '', true, true);

unset($user['salt']);
unset($user['hash']);

jsonResponse([
    'success' => true,
    'message' => '登录成功',
    'user' => $user
]);
?>