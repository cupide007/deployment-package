<?php
// 创建银行账户云函数 - PHP版本
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
    
    if ($now > $expiresAt) {
        $db->delete("session_$sessionId");
        return null;
    }
    
    return $session;
}

// 生成账号
function generateAccountNumber() {
    return 'ANT-' . strtoupper(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8));
}

try {
    // 创建数据库实例
    $db = new Database('antister_virtual_country');
    
    // 处理请求
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
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
        
        // 检查是否已存在银行账户
        $existingAccountData = $db->get("bank_account_{$session['userId']}");
        if ($existingAccountData) {
            http_response_code(400);
            echo json_encode([
                'error' => '您已拥有银行账户'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 创建新银行账户
        $newAccount = [
            'accountNumber' => generateAccountNumber(),
            'balance' => 0,
            'transactions' => [],
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
        
        // 保存银行账户
        $db->set("bank_account_{$session['userId']}", json_encode($newAccount));
        
        // 返回成功响应
        http_response_code(201);
        echo json_encode([
            'message' => '银行账户创建成功',
            'account' => $newAccount
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // 不支持的请求方法
        http_response_code(405);
        echo json_encode([
            'error' => "Unsupported method ('$method')"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('创建银行账户失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '创建银行账户失败，请稍后重试',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
