<?php
// 更新账户余额云函数 - PHP版本
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
        
        $amount = $data['amount'] ?? null;
        $type = $data['type'] ?? '';
        $description = $data['description'] ?? '';
        
        if ($amount === null || empty($type) || empty($description)) {
            http_response_code(400);
            echo json_encode(['error' => '请提供完整的交易信息'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($type !== 'deposit' && $type !== 'withdrawal') {
            http_response_code(400);
            echo json_encode(['error' => '交易类型无效'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => '交易金额必须大于0'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $accountData = $db->get("bank_account_{$session['userId']}");
        if (!$accountData) {
            http_response_code(404);
            echo json_encode(['error' => '银行账户不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $account = json_decode($accountData, true);
        $newBalance = $account['balance'];
        
        if ($type === 'deposit') {
            $newBalance += $amount;
        } else if ($type === 'withdrawal') {
            if ($amount > $account['balance']) {
                http_response_code(400);
                echo json_encode(['error' => '余额不足'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $newBalance -= $amount;
        }
        
        $account['balance'] = $newBalance;
        $account['updatedAt'] = date('c');
        $db->set("bank_account_{$session['userId']}", json_encode($account));
        
        http_response_code(200);
        echo json_encode(['message' => '账户余额更新成功', 'account' => $account], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('更新账户余额失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '更新账户余额失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
