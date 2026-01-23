<?php
// 获取用户信息云函数
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

try {
    $db = new Database('antister_virtual_country');
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // 获取Session ID
        $sessionId = $_COOKIE['sessionId'] ?? null;
        if (isset($_SERVER['HTTP_X_SESSION_ID'])) {
            $sessionId = $_SERVER['HTTP_X_SESSION_ID'];
        }
        
        // 验证Session
        $session = verifySession($db, $sessionId);
        if (!$session) {
            http_response_code(401);
            echo json_encode(['error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取请求参数
        $userId = $_GET['userId'] ?? null;
        
        // 验证目标用户ID
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => '请提供用户ID'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 检查权限：只能访问自己的数据，或者管理员可以访问所有数据
        $currentUserData = $db->get("user_{$session['userId']}");
        $currentUser = json_decode($currentUserData, true);
        
        if ($currentUser['id'] !== $userId && $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => '无权访问该用户信息'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取目标用户数据
        $userData = $db->get("user_$userId");
        if (!$userData) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $user = json_decode($userData, true);
        
        // 返回用户信息（不包含敏感信息）
        http_response_code(200);
        echo json_encode([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? 'user',
                'createdAt' => $user['createdAt'] ?? null,
                'lastLogin' => $user['lastLogin'] ?? null
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('获取用户信息失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '获取用户信息失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
