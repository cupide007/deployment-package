<?php
// 用户登录云函数 - PHP版本
// 使用 Retinbox Database 类

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 密码验证函数
function verifyPassword($password, $salt, $hash) {
    $verifyHash = hash_pbkdf2('sha512', $password, $salt, 1000, 64);
    return $hash === $verifyHash;
}

// 生成Session ID
function generateSessionId() {
    return bin2hex(random_bytes(32));
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
        
        // 如果 JSON 解析失败，尝试从 $_POST 获取 (兼容某些平台环境)
        if (empty($data)) {
            $data = $_POST;
        }
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        // 进一步尝试从 $_REQUEST 获取 (作为最后手段)
        if (empty($email) && isset($_REQUEST['email'])) $email = $_REQUEST['email'];
        if (empty($password) && isset($_REQUEST['password'])) $password = $_REQUEST['password'];
        
        // 验证输入
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'error' => '请提供邮箱和密码',
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
        
        // 获取所有用户ID
        $usersData = $db->get('users');
        $userIds = $usersData ? json_decode($usersData, true) : [];
        
        // 查找用户
        $user = null;
        foreach ($userIds as $userId) {
            $userData = $db->get("user_$userId");
            if ($userData) {
                try {
                    $parsedUser = json_decode($userData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue; // 跳过此用户，继续查找其他用户
                    }
                    // 支持通过邮箱或用户名登录
                    if ($parsedUser['email'] === $email || $parsedUser['username'] === $email) {
                        $user = $parsedUser;
                        break;
                    }
                } catch (Exception $e) {
                    continue; // 跳过此用户，继续查找其他用户
                }
            }
        }
        
        // 验证用户和密码
        if (!$user || !verifyPassword($password, $user['salt'], $user['hash'])) {
            http_response_code(401);
            echo json_encode([
                'error' => '邮箱/用户名或密码错误'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 更新最后登录时间
        $user['lastLogin'] = date('c');
        $db->set("user_{$user['id']}", json_encode($user));
        
        // 生成Session ID
        $sessionId = generateSessionId();
        
        // 保存Session (7天过期)
        $session = [
            'userId' => $user['id'],
            'createdAt' => date('c'),
            'expiresAt' => date('c', time() + 7 * 24 * 60 * 60)
        ];
        $db->set("session_$sessionId", json_encode($session));
        
        // 设置Session Cookie
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('sessionId', $sessionId, [
            'expires' => time() + 7 * 24 * 60 * 60,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // 返回成功响应
        http_response_code(200);
        echo json_encode([
            'message' => '登录成功',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // 不支持的请求方法
        http_response_code(405);
        echo json_encode([
            'error' => "Unsupported method ('$method')"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('登录失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '登录失败，请稍后重试',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
