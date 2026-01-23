<?php
// 添加借书记录云函数 - PHP版本
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

function generateRecordId() {
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
        
        $bookId = $data['bookId'] ?? '';
        $bookTitle = $data['bookTitle'] ?? '';
        
        if (empty($bookId) || empty($bookTitle)) {
            http_response_code(400);
            echo json_encode(['error' => '请提供完整的借阅信息'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $recordId = generateRecordId();
        $borrowDate = date('c');
        $dueDate = date('c', time() + 14 * 24 * 60 * 60); // 14天到期
        
        $newRecord = [
            'recordId' => $recordId,
            'bookId' => $bookId,
            'bookTitle' => $bookTitle,
            'borrowDate' => $borrowDate,
            'dueDate' => $dueDate,
            'returnDate' => null,
            'status' => 'borrowed'
        ];
        
        $db->set("library_records_{$session['userId']}_$recordId", json_encode($newRecord));
        
        $recordIdsData = $db->get("library_records_{$session['userId']}");
        $recordIds = $recordIdsData ? json_decode($recordIdsData, true) : [];
        $recordIds[] = $recordId;
        $db->set("library_records_{$session['userId']}", json_encode($recordIds));
        
        http_response_code(201);
        echo json_encode(['message' => '借阅记录添加成功', 'record' => $newRecord], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('添加借阅记录失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '添加借阅记录失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
