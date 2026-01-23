<?php
// 保存管理员权限云函数
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
        
        // 获取请求体中的管理员权限数据
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        $permissions = $data['permissions'] ?? null;
        
        if (!$permissions || !is_array($permissions)) {
            http_response_code(400);
            echo json_encode(['error' => '管理员权限数据格式错误'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 保存管理员权限数据
        $db->set('admin_permissions', json_encode($permissions, JSON_UNESCAPED_UNICODE));
        
        // 返回成功响应
        http_response_code(200);
        echo json_encode([
            'message' => '管理员权限保存成功',
            'permissions' => $permissions
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('保存管理员权限失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '保存管理员权限失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
