<?php
// 保存游戏进度云函数 - PHP版本
header('Content-Type: application/json; charset=utf-8');

function verifySession($db, $sessionId) {
    if (!$sessionId) return null;
    $sessionData = $db->get("session_$sessionId");
    if (!$sessionData) return null;
    try {
        $session = json_decode($sessionData, true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;
    } catch (Exception $e) { return null; }
    if (empty($session['expiresAt']) || empty($session['userId'])) return null;
    $now = new DateTime();
    try { $expiresAt = new DateTime($session['expiresAt']); } 
    catch (Exception $e) { $db->delete("session_$sessionId"); return null; }
    if ($now > $expiresAt) { $db->delete("session_$sessionId"); return null; }
    return $session;
}

try {
    $db = new Database('antister_virtual_country');
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $sessionId = $_COOKIE['sessionId'] ?? null;
        if (isset($_SERVER['HTTP_X_SESSION_ID'])) $sessionId = $_SERVER['HTTP_X_SESSION_ID'];
        
        $session = verifySession($db, $sessionId);
        if (!$session) {
            http_response_code(401);
            echo json_encode(['error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $input = file_get_contents('php://input');
        $progressData = json_decode($input, true);
        
        if (!$progressData) {
            http_response_code(400);
            echo json_encode(['error' => '请提供游戏进度数据'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取当前游戏进度
        $currentProgressData = $db->get("game_progress_{$session['userId']}");
        $currentProgress = $currentProgressData ? json_decode($currentProgressData, true) : [];
        
        // 合并现有数据和新数据
        $updatedProgress = array_merge($currentProgress, $progressData);
        $updatedProgress['updatedAt'] = date('c');
        
        $db->set("game_progress_{$session['userId']}", json_encode($updatedProgress));
        
        http_response_code(200);
        echo json_encode(['message' => '游戏进度保存成功', 'progress' => $updatedProgress], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('保存游戏进度失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '保存游戏进度失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
