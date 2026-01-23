<?php
// 获取当前用户云函数 - PHP版本
// 使用 Retinbox Database 类

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 创建数据库实例
    $db = new Database('antister_virtual_country');
    
    // 处理请求
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // 获取 Session ID
        $sessionId = null;
        
        // 从 Cookie 获取
        if (isset($_COOKIE['sessionId'])) {
            $sessionId = $_COOKIE['sessionId'];
        }
        
        // 从 Header 获取（优先级更高）
        if (isset($_SERVER['HTTP_X_SESSION_ID'])) {
            $sessionId = $_SERVER['HTTP_X_SESSION_ID'];
        }
        
        if (!$sessionId) {
            http_response_code(401);
            echo json_encode([
                'error' => '未登录或登录已过期'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 验证 Session
        $sessionData = $db->get("session_$sessionId");
        if (!$sessionData) {
            http_response_code(401);
            echo json_encode([
                'error' => '未登录或登录已过期'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 解析 Session 数据
        try {
            $session = json_decode($sessionData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON 解析失败: ' . json_last_error_msg());
            }
        } catch (Exception $parseError) {
            error_log('解析 session 数据失败: ' . $parseError->getMessage());
            http_response_code(401);
            echo json_encode([
                'error' => '会话数据损坏'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 验证 session 数据完整性
        if (empty($session['expiresAt']) || empty($session['userId'])) {
            error_log('Session 数据不完整: ' . json_encode($session));
            http_response_code(401);
            echo json_encode([
                'error' => '会话数据不完整'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 检查 Session 是否过期
        $now = new DateTime();
        try {
            $expiresAt = new DateTime($session['expiresAt']);
        } catch (Exception $e) {
            error_log('Session 过期时间格式无效: ' . $session['expiresAt']);
            // 删除无效的 session 数据
            $db->delete("session_$sessionId");
            http_response_code(401);
            echo json_encode([
                'error' => '会话数据无效'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 检查是否过期
        if ($now > $expiresAt) {
            // 删除过期 Session
            $db->delete("session_$sessionId");
            http_response_code(401);
            echo json_encode([
                'error' => '登录已过期，请重新登录'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取用户数据
        $userData = $db->get("user_{$session['userId']}");
        if (!$userData) {
            // 用户不存在，删除无效 Session
            $db->delete("session_$sessionId");
            http_response_code(404);
            echo json_encode([
                'error' => '用户不存在'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 解析用户数据
        try {
            $user = json_decode($userData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON 解析失败: ' . json_last_error_msg());
            }
        } catch (Exception $parseError) {
            error_log('解析用户数据失败: ' . $parseError->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => '用户数据损坏'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 返回用户信息（不包含敏感信息）
        http_response_code(200);
        echo json_encode([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'createdAt' => $user['createdAt'],
                'lastLogin' => $user['lastLogin'] ?? null
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
    error_log('获取当前用户失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '获取用户信息失败，请稍后重试',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
