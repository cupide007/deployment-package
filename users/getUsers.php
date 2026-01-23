<?php
// 获取用户列表云函数
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
        
        // 获取当前用户信息
        $currentUserData = $db->get("user_{$session['userId']}");
        $currentUser = json_decode($currentUserData, true);
        
        // 获取所有用户ID
        $userIdsData = $db->get('users');
        $userIds = $userIdsData ? json_decode($userIdsData, true) : [];
        
        // 获取所有用户信息
        $users = [];
        foreach ($userIds as $userId) {
            $userData = $db->get("user_$userId");
            if ($userData) {
                $user = json_decode($userData, true);
                
                // 根据用户角色返回不同级别的信息
                if (isset($currentUser['role']) && $currentUser['role'] === 'admin') {
                    // 管理员：返回完整用户信息
                    $users[] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'] ?? null,
                        'role' => $user['role'] ?? 'user',
                        'createdAt' => $user['createdAt'] ?? null,
                        'lastLogin' => $user['lastLogin'] ?? null
                    ];
                } else {
                    // 普通用户：只返回基本的公开信息，用于转账等功能
                    $users[] = [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ];
                }
            }
        }
        
        // 返回用户列表
        http_response_code(200);
        echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('获取用户列表失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '获取用户列表失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
