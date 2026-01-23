<?php
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
        $transactions = $data['transactions'] ?? null;
        if (is_string($transactions)) {
            $decodedTransactions = json_decode($transactions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $transactions = $decodedTransactions;
            }
        }
        if (!is_array($transactions)) {
            $transactions = $transactions === null ? [] : [$transactions];
        }
        
        $existingIdsData = $db->get("transactions_{$session['userId']}");
        $existingIds = $existingIdsData ? json_decode($existingIdsData, true) : [];
        
        $transactionIds = [];
        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) continue;
            $transactionId = $transaction['id'] ?? $transaction['transactionId'] ?? generateTransactionId();
            $transaction['id'] = $transactionId;
            if (empty($transaction['transactionId'])) {
                $transaction['transactionId'] = $transactionId;
            }
            if (empty($transaction['timestamp'])) {
                $transaction['timestamp'] = date('c');
            }
            $db->set("transactions_{$session['userId']}_$transactionId", json_encode($transaction, JSON_UNESCAPED_UNICODE));
            $transactionIds[] = $transactionId;
        }
        
        foreach ($existingIds as $existingId) {
            if (!in_array($existingId, $transactionIds, true)) {
                $db->delete("transactions_{$session['userId']}_$existingId");
            }
        }
        
        $db->set("transactions_{$session['userId']}", json_encode($transactionIds));
        
        http_response_code(200);
        echo json_encode([
            'message' => '交易记录保存成功',
            'transactions' => $transactions
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('保存交易记录失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '保存交易记录失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
