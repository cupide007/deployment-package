<?php
// 获取游戏进度云函数 - PHP版本
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
    
    if ($method === 'GET') {
        $sessionId = $_COOKIE['sessionId'] ?? null;
        if (isset($_SERVER['HTTP_X_SESSION_ID'])) $sessionId = $_SERVER['HTTP_X_SESSION_ID'];
        
        $session = verifySession($db, $sessionId);
        if (!$session) {
            http_response_code(401);
            echo json_encode(['error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $progressData = $db->get("game_progress_{$session['userId']}");
        
        if ($progressData) {
            $progress = json_decode($progressData, true);
            http_response_code(200);
            echo json_encode(['progress' => $progress], JSON_UNESCAPED_UNICODE);
        } else {
            // 返回默认游戏进度
            $defaultProgress = [
                'level' => 1,
                'experience' => 0,
                'achievements' => [],
                'quests' => new stdClass(),
                'inventory' => ['gold' => 0, 'items' => []],
                'gameStats' => ['play_time' => 0, 'missions_completed' => 0, 'enemies_defeated' => 0],
                'updatedAt' => date('c')
            ];
            http_response_code(200);
            echo json_encode(['progress' => $defaultProgress], JSON_UNESCAPED_UNICODE);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('获取游戏进度失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '获取游戏进度失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
