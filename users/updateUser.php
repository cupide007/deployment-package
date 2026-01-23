<?php
// 更新用户信息云函数
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
    
    if ($method === 'POST') {
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
        
        // 获取请求数据
        $rawInput = file_get_contents('php://input');
        $trimmedInput = trim($rawInput);
        $updateData = [];

        if ($trimmedInput !== '') {
            $decodedInput = json_decode($trimmedInput, true);
            if (json_last_error() === JSON_ERROR_NONE && $decodedInput !== null) {
                $updateData = $decodedInput;
            }
        }

        if (empty($updateData)) {
            $updateData = $_POST;
        }

        if (empty($updateData)) {
            $updateData = $_REQUEST;
        }
        
        // 获取当前用户数据
        $userData = $db->get("user_{$session['userId']}");
        if (!$userData) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $user = json_decode($userData, true);
        
        // 只允许更新特定字段，不允许更新敏感字段
        $allowedFields = ['username', 'email', 'qq', 'gender', 'race', 'age', 'residence', 'bio', 'avatar'];
        $filteredUpdateData = [];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $updateData)) {
                $filteredUpdateData[$field] = $updateData[$field];
            }
        }

        if (empty($filteredUpdateData)) {
            http_response_code(400);
            echo json_encode(['error' => '请提供要更新的数据'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 更新用户数据
        $updatedUser = array_merge($user, $filteredUpdateData);
        $db->set("user_{$session['userId']}", json_encode($updatedUser, JSON_UNESCAPED_UNICODE));
        
        // 返回更新后的用户信息（不包含敏感信息）
        http_response_code(200);
        echo json_encode([
            'message' => '用户信息更新成功',
            'user' => [
                'id' => $updatedUser['id'],
                'username' => $updatedUser['username'],
                'email' => $updatedUser['email'] ?? null,
                'role' => $updatedUser['role'] ?? 'user',
                'createdAt' => $updatedUser['createdAt'] ?? null,
                'lastLogin' => $updatedUser['lastLogin'] ?? null
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('更新用户信息失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '更新用户信息失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
