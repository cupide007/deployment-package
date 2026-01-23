<?php
// 更新用户设置云函数
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
        $settingsData = json_decode($rawInput, true);
        
        // 验证设置数据
        if (!$settingsData) {
            http_response_code(400);
            echo json_encode(['error' => '请提供用户设置数据'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取当前用户设置
        $currentSettingsData = $db->get("user_settings_{$session['userId']}");
        $currentSettings = [];
        
        if ($currentSettingsData) {
            $currentSettings = json_decode($currentSettingsData, true);
            if (!is_array($currentSettings)) {
                $currentSettings = [];
            }
        }
        
        // 更新用户设置，合并现有数据和新数据
        $updatedSettings = array_merge($currentSettings, $settingsData);
        $updatedSettings['updatedAt'] = date('c');
        
        // 保存更新后的用户设置
        $db->set("user_settings_{$session['userId']}", json_encode($updatedSettings, JSON_UNESCAPED_UNICODE));
        
        // 返回成功响应
        http_response_code(200);
        echo json_encode([
            'message' => '用户设置更新成功',
            'settings' => $updatedSettings
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('更新用户设置失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '更新用户设置失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
