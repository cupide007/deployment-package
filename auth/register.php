<?php
// 用户注册云函数 - PHP版本
// 使用 Retinbox Database 类

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 生成唯一ID
function generateId() {
    return time() . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 9);
}

// 密码加密
function hashPassword($password) {
    $salt = bin2hex(random_bytes(16));
    $hash = hash_pbkdf2('sha512', $password, $salt, 1000, 64);
    return ['salt' => $salt, 'hash' => $hash];
}

try {
    // 创建数据库实例
    $db = new Database('antister_virtual_country');
    
    // 处理请求
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // 获取请求数据
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // 如果 JSON 解析失败，尝试从 $_POST 获取
        if (empty($data)) {
            $data = $_POST;
        }
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $username = $data['username'] ?? '';
        
        // 进一步尝试从 $_REQUEST 获取
        if (empty($email) && isset($_REQUEST['email'])) $email = $_REQUEST['email'];
        if (empty($password) && isset($_REQUEST['password'])) $password = $_REQUEST['password'];
        if (empty($username) && isset($_REQUEST['username'])) $username = $_REQUEST['username'];
        
        // 验证输入
        if (empty($email) || empty($password) || empty($username)) {
            http_response_code(400);
            echo json_encode([
                'error' => '请提供完整的注册信息',
                'debug' => [
                    'method' => $method,
                    'has_input' => !empty($input),
                    'input_length' => strlen($input),
                    'has_post' => !empty($_POST),
                    'has_request' => !empty($_REQUEST)
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取用户ID列表
        $usersData = $db->get('users');
        $userIds = $usersData ? json_decode($usersData, true) : [];
        
        // 检查邮箱和用户名是否已存在
        foreach ($userIds as $userId) {
            $userData = $db->get("user_$userId");
            if ($userData) {
                try {
                    $existingUser = json_decode($userData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }
                    if ($existingUser['email'] === $email) {
                        http_response_code(400);
                        echo json_encode([
                            'error' => '该邮箱已被注册'
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    if ($existingUser['username'] === $username) {
                        http_response_code(400);
                        echo json_encode([
                            'error' => '该用户名已被使用'
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 生成用户ID
        $userId = generateId();
        
        // 加密密码
        $passwordData = hashPassword($password);
        
        // 创建用户对象
        $user = [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'salt' => $passwordData['salt'],
            'hash' => $passwordData['hash'],
            'role' => 'user',
            'createdAt' => date('c'),
            'lastLogin' => null
        ];
        
        // 保存用户数据
        $db->set("user_$userId", json_encode($user));
        
        // 更新用户列表
        $userIds[] = $userId;
        $db->set('users', json_encode($userIds));
        
        // 创建默认游戏进度
        $gameProgress = [
            'level' => 1,
            'experience' => 0,
            'achievements' => [],
            'quests' => new stdClass(),
            'inventory' => ['gold' => 0, 'items' => []],
            'gameStats' => ['play_time' => 0, 'missions_completed' => 0, 'enemies_defeated' => 0],
            'updatedAt' => date('c')
        ];
        $db->set("game_progress_$userId", json_encode($gameProgress));
        
        // 创建默认用户设置
        $userSettings = [
            'theme' => 'light',
            'language' => 'zh-CN',
            'notifications' => ['email' => true, 'system' => true, 'activity' => false],
            'notificationFrequency' => 'immediately',
            'privacySettings' => ['showEmail' => false, 'showQQ' => false, 'allowFriendRequests' => true],
            'displaySettings' => ['showLevel' => true, 'showAchievements' => true],
            'updatedAt' => date('c')
        ];
        $db->set("user_settings_$userId", json_encode($userSettings));
        
        // 返回成功响应
        http_response_code(201);
        echo json_encode([
            'message' => '注册成功',
            'userId' => $userId
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // 不支持的请求方法
        http_response_code(405);
        echo json_encode([
            'error' => "Unsupported method ('$method')"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('注册失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '注册失败，请稍后重试',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
