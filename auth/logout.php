<?php
// 用户登出云函数 - PHP版本
// 使用 Retinbox Database 类

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 创建数据库实例
    $db = new Database('antister_virtual_country');
    
    // 处理请求
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
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
        
        if ($sessionId) {
            // 删除 Session
            $db->delete("session_$sessionId");
            
            // 清除 Cookie
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('sessionId', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        // 返回成功响应
        http_response_code(200);
        echo json_encode([
            'message' => '登出成功'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // 不支持的请求方法
        http_response_code(405);
        echo json_encode([
            'error' => "Unsupported method ('$method')"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('登出失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '登出失败，请稍后重试',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
