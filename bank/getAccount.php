<?php
// 获取银行账户云函数 - PHP版本
// 使用 Retinbox Database 类

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 验证Session
function verifySession($db, $sessionId) {
    if (!$sessionId) {
        return null;
    }
    
    $sessionData = $db->get("session_$sessionId");
    if (!$sessionData) {
        return null;
    }
    
    try {
        $session = json_decode($sessionData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
    } catch (Exception $e) {
        return null;
    }
    
    // 验证session数据完整性
    if (empty($session['expiresAt']) || empty($session['userId'])) {
        return null;
    }
    
    $now = new DateTime();
    try {
        $expiresAt = new DateTime($session['expiresAt']);
    } catch (Exception $e) {
        $db->delete("session_$sessionId");
        return null;
    }
    
    // 检查Session是否过期
    if ($now > $expiresAt) {
        $db->delete("session_$sessionId");
        return null;
    }
    
    return $session;
}

try {
    // 创建数据库实例
    $db = new Database('antister_virtual_country');
    
    // 处理请求
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // 获取 Session ID
        $sessionId = $_COOKIE['sessionId'] ?? null;
        if (isset($_SERVER['HTTP_X_SESSION_ID'])) {
            $sessionId = $_SERVER['HTTP_X_SESSION_ID'];
        }
        
        // 验证Session
        $session = verifySession($db, $sessionId);
        if (!$session) {
            http_response_code(401);
            echo json_encode([
                'error' => '未登录或登录已过期'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取银行账户数据
        $accountData = $db->get("bank_account_{$session['userId']}");
        
        if ($accountData) {
            $account = json_decode($accountData, true);
            http_response_code(200);
            echo json_encode([
                'account' => $account
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // 返回默认银行账户
            $defaultAccount = [
                'accountNumber' => 'ANT-' . strtoupper(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8)),
                'balance' => 0,
                'transactions' => [],
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
            http_response_code(200);
            echo json_encode([
                'account' => $defaultAccount
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } else {
        // 不支持的请求方法
        http_response_code(405);
        echo json_encode([
            'error' => "Unsupported method ('$method')"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('获取银行账户失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '获取银行账户失败，请稍后重试',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
