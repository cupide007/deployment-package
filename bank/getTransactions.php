<?php
// 获取交易记录云函数 - PHP版本
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
        
        // 获取交易记录ID列表
        $transactionIdsData = $db->get("transactions_{$session['userId']}");
        $transactionIds = $transactionIdsData ? json_decode($transactionIdsData, true) : [];
        
        // 获取所有交易记录
        $transactions = [];
        foreach ($transactionIds as $transactionId) {
            $transactionData = $db->get("transactions_{$session['userId']}_$transactionId");
            if ($transactionData) {
                $transactions[] = json_decode($transactionData, true);
            }
        }
        
        // 按时间倒序排序
        usort($transactions, function($a, $b) {
            return strtotime($b['timestamp'] ?? 0) - strtotime($a['timestamp'] ?? 0);
        });
        
        http_response_code(200);
        echo json_encode(['transactions' => $transactions], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('获取交易记录失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '获取交易记录失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
