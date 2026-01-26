<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('请求方法不允许', 405);
}

$data = getJsonInput();
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$email || !$password) {
    jsonError('请填写完整信息');
}

if ($db->get("idx_email_" . md5($email))) {
    jsonError('该邮箱已被注册');
}

if ($db->get("idx_username_" . md5($username))) {
    jsonError('该用户名已被占用');
}

$userId = 'user_' . time() . '_' . rand(1000, 9999);
$salt = bin2hex(random_bytes(16));
$hash = hash_pbkdf2('sha512', $password, $salt, 1000, 64);

$newUser = [
    'id' => $userId,
    'username' => $username,
    'email' => $email,
    'salt' => $salt,
    'hash' => $hash,
    'role' => 'citizen',
    'createdAt' => date('c'),
    'lastLogin' => null
];

try {
    $db->set($userId, json_encode($newUser));
    $db->set("idx_email_" . md5($email), $userId);
    $db->set("idx_username_" . md5($username), $userId);

    $usersList = json_decode($db->get('sys_users_list') ?: '[]', true);
    $usersList[] = $userId;
    $db->set('sys_users_list', json_encode($usersList));

    $db->set("bank_account_" . $userId, json_encode(['balance' => 0, 'cards' => []]));

    jsonResponse(['success' => true, 'message' => '注册成功', 'user' => $newUser]);

} catch (Exception $e) {
    jsonError('注册失败: ' . $e->getMessage(), 500);
}
?>