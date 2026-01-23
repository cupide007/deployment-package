<?php
// 数据库初始化云函数 - PHP版本
// 使用 Retinbox Database 类

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 创建数据库实例
    $db = new Database('antister_virtual_country');
    
    // 处理请求
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // 初始化数据库
        
        // 检查是否已经初始化
        $usersData = $db->get('users');
        $userIds = $usersData ? json_decode($usersData, true) : [];
        
        // 检查是否已存在admin账号
        $adminExists = false;
        foreach ($userIds as $userId) {
            $userData = $db->get("user_$userId");
            if ($userData) {
                $user = json_decode($userData, true);
                if ($user['username'] === 'admin' || $user['email'] === 'admin@antister.com') {
                    $adminExists = true;
                    break;
                }
            }
        }
        
        if (!$adminExists) {
            // 创建管理员账号
            $adminId = 'admin_' . time();
            $password = 'test';
            
            // 加密密码
            $salt = bin2hex(random_bytes(16));
            $hash = hash_pbkdf2('sha512', $password, $salt, 1000, 64);
            
            // 创建管理员用户对象
            $adminUser = [
                'id' => $adminId,
                'username' => 'admin',
                'email' => 'admin@antister.com',
                'salt' => $salt,
                'hash' => $hash,
                'role' => 'admin',
                'createdAt' => date('c'),
                'lastLogin' => null
            ];
            
            // 保存管理员数据
            $db->set("user_$adminId", json_encode($adminUser));
            
            // 更新用户列表
            $userIds[] = $adminId;
            $db->set('users', json_encode($userIds));
            
            // 创建管理员的默认数据
            $gameProgress = [
                'level' => 99,
                'experience' => 999999,
                'achievements' => [],
                'quests' => new stdClass(),
                'inventory' => ['gold' => 999999, 'items' => []],
                'gameStats' => [
                    'play_time' => 0,
                    'missions_completed' => 0,
                    'enemies_defeated' => 0
                ],
                'updatedAt' => date('c')
            ];
            $db->set("game_progress_$adminId", json_encode($gameProgress));
            
            $userSettings = [
                'theme' => 'light',
                'language' => 'zh-CN',
                'notifications' => [
                    'email' => true,
                    'system' => true,
                    'activity' => true
                ],
                'notificationFrequency' => 'immediately',
                'privacySettings' => [
                    'showEmail' => false,
                    'showQQ' => false,
                    'allowFriendRequests' => false
                ],
                'displaySettings' => [
                    'showLevel' => true,
                    'showAchievements' => true
                ],
                'updatedAt' => date('c')
            ];
            $db->set("user_settings_$adminId", json_encode($userSettings));
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => '数据库初始化完成',
                'adminCreated' => true,
                'admin' => [
                    'username' => 'admin',
                    'password' => 'test',
                    'email' => 'admin@antister.com',
                    'role' => 'admin'
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => '数据库已初始化',
                'adminCreated' => false
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } elseif ($method === 'GET') {
        // 查询初始化状态
        $usersData = $db->get('users');
        $userIds = $usersData ? json_decode($usersData, true) : [];
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'initialized' => !empty($usersData),
            'userCount' => count($userIds),
            'message' => $usersData ? '数据库已初始化' : '数据库未初始化，请使用 POST 方法进行初始化'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // 不支持的请求方法
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => "Unsupported method ('$method')"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('初始化失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '初始化失败',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
