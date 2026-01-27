<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$data = getJsonInput();
if (empty($data)) {
    $data = $_GET;
}

$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$email || !$password) {
    jsonError('请填写完整信息（用户名、邮箱、密码）');
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
    'role' => $data['role'] ?? 'citizen',
    'qq' => trim($data['qq'] ?? ''),
    'gender' => $data['gender'] ?? '其他',
    'race' => trim($data['race'] ?? ''),
    'age' => trim($data['age'] ?? ''),
    'residence' => trim($data['residence'] ?? ''),
    'bio' => trim($data['bio'] ?? ''),
    'createdAt' => date('-m-d H:i:s'),
    'lastLogin' => null
];

try {
    $db->set($userId, json_encode($newUser));

    $db->set("idx_email_" . md5($email), $userId);
    $db->set("idx_username_" . md5($username), $userId);

    $usersListRaw = $db->get('sys_users_list');
    $usersList = $usersListRaw ? json_decode($usersListRaw, true) : [];
    $usersList[] = $userId;
    $db->set('sys_users_list', json_encode($usersList));

    $db->set("bank_account_" . $userId, json_encode(['balance' => 0, 'cards' => []]));

    unset($newUser['salt']);
    unset($newUser['hash']);
    jsonResponse(['success' => true, 'message' => '添加成功', 'user' => $newUser]);

} catch (Exception $e) {
    jsonError('操作失败: ' . $e->getMessage(), 500);
}
?>