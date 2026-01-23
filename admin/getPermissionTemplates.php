<?php
// 获取权限模板云函数
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
        
        // 获取权限模板数据
        $templatesData = $db->get('permission_templates');
        
        if ($templatesData) {
            $templates = json_decode($templatesData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(500);
                echo json_encode(['error' => '权限模板数据格式错误'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            http_response_code(200);
            echo json_encode(['templates' => $templates], JSON_UNESCAPED_UNICODE);
        } else {
            // 返回默认权限模板数组
            http_response_code(200);
            echo json_encode(['templates' => []], JSON_UNESCAPED_UNICODE);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('获取权限模板失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '获取权限模板失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
