<?php
require_once 'common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($db->get("idx_username_" . md5("admin"))) {
        jsonResponse(['success' => true, 'message' => '数据库已初始化']);
    }

    $adminId = 'user_admin';
    $password = 'test';
    $salt = bin2hex(random_bytes(16));
    $hash = hash_pbkdf2('sha512', $password, $salt, 1000, 64);

    $adminUser = [
        'id' => $adminId,
        'username' => 'admin',
        'email' => 'admin@antister.com',
        'salt' => $salt,
        'hash' => $hash,
        'role' => 'admin',
        'createdAt' => date('c')
    ];

    $db->set($adminId, json_encode($adminUser));
    $db->set("idx_username_" . md5("admin"), $adminId);
    $db->set("idx_email_" . md5("admin@antister.com"), $adminId);

    $usersList = json_decode($db->get('sys_users_list') ?: '[]', true);
    $usersList[] = $adminId;
    $db->set('sys_users_list', json_encode($usersList));

    jsonResponse([
        'success' => true,
        'message' => '初始化成功',
        'admin' => ['username' => 'admin', 'password' => 'test']
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $isInit = $db->get("idx_username_" . md5("admin")) ? true : false;
    jsonResponse([
        'success' => true,
        'initialized' => $isInit,
        'message' => $isInit ? '已初始化' : '未初始化'
    ]);
} else {
    jsonError('请求方法不允许', 405);
}
?>