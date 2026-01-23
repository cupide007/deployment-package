<?php
// 保存银行卡片云函数 - PHP版本
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
        $data = json_decode($input, true);
        $cards = $data['cards'] ?? null;
        
        if (!$cards || !is_array($cards)) {
            http_response_code(400);
            echo json_encode(['error' => '卡片数据格式错误'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db->set("bank_cards_{$session['userId']}", json_encode($cards));
        
        http_response_code(200);
        echo json_encode(['message' => '银行卡片保存成功', 'cards' => $cards], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('保存银行卡片失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '保存银行卡片失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
