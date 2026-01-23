<?php
// 添加交易记录云函数 - PHP版本
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

function generateTransactionId() {
    return time() . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 9);
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
        $normalizedType = $type === 'withdrawal' ? 'withdraw' : $type;
        
        // 验证请求数据
        if ($amount === null || empty($type) || empty($description)) {
            http_response_code(400);
            echo json_encode(['error' => '请提供完整的交易信息'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($normalizedType !== 'deposit' && $normalizedType !== 'withdraw') {
            http_response_code(400);
            echo json_encode(['error' => '交易类型无效'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => '交易金额必须大于0'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 获取银行账户数据
        $accountData = $db->get("bank_account_{$session['userId']}");
        if (!$accountData) {
            http_response_code(404);
            echo json_encode(['error' => '银行账户不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $account = json_decode($accountData, true);
        $newBalance = $account['balance'];
        
        // 更新余额
        if ($normalizedType === 'deposit') {
            $newBalance += $amount;
        } else if ($normalizedType === 'withdraw') {
            if ($amount > $account['balance']) {
                http_response_code(400);
                echo json_encode(['error' => '余额不足'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $newBalance -= $amount;
        }
        
        // 创建新交易记录
        $transactionId = generateTransactionId();
        $newTransaction = [
            'transactionId' => $transactionId,
            'accountId' => $session['userId'],
            'type' => $normalizedType,
            'amount' => $amount,
            'description' => $description,
            'balance' => $newBalance,
            'timestamp' => date('c')
        ];
        
        // 保存新交易
        $db->set("transactions_{$session['userId']}_$transactionId", json_encode($newTransaction));
        
        // 更新交易ID列表
        $transactionIdsData = $db->get("transactions_{$session['userId']}");
        $transactionIds = $transactionIdsData ? json_decode($transactionIdsData, true) : [];
        $transactionIds[] = $transactionId;
        $db->set("transactions_{$session['userId']}", json_encode($transactionIds));
        
        // 更新账户余额
        $account['balance'] = $newBalance;
        $account['updatedAt'] = date('c');
        $db->set("bank_account_{$session['userId']}", json_encode($account));
        
        http_response_code(201);
        echo json_encode([
            'message' => '交易记录添加成功',
            'transaction' => $newTransaction,
            'account' => $account
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('添加交易记录失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '添加交易记录失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
