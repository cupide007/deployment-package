<?php
// 获取文档数据云函数 - PHP版本
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
        
        $usersData = $db->get('users');
        $userIds = $usersData ? json_decode($usersData, true) : [];
        if (!is_array($userIds)) {
            $userIds = $userIds === null ? [] : [$userIds];
        }
        if (!in_array($session['userId'], $userIds, true)) {
            $userIds[] = $session['userId'];
        }
        $normalizedUserIds = [];
        foreach ($userIds as $userId) {
            if ($userId === null || $userId === '') {
                continue;
            }
            if (!in_array($userId, $normalizedUserIds, true)) {
                $normalizedUserIds[] = $userId;
            }
        }
        $allDocuments = [];
        $seenDocuments = [];
        foreach ($normalizedUserIds as $userId) {
            $documentsData = $db->get("documents_{$userId}");
            if (!$documentsData) {
                continue;
            }
            $documents = json_decode($documentsData, true);
            if (is_string($documents)) {
                $decodedDocuments = json_decode($documents, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $documents = $decodedDocuments;
                }
            }
            if (!is_array($documents)) {
                $documents = $documents === null ? [] : [$documents];
            }
            foreach ($documents as $document) {
                if (!is_array($document)) {
                    continue;
                }
                if (empty($document['fileUrl']) && !empty($document['fileName'])) {
                    $document['fileUrl'] = 'document-file.php?name=' . $document['fileName'];
                }
                $documentKey = $document['id'] ?? null;
                if ($documentKey === null || isset($seenDocuments[$documentKey])) {
                    continue;
                }
                $seenDocuments[$documentKey] = true;
                $allDocuments[] = $document;
            }
        }
        http_response_code(200);
        echo json_encode(['documents' => $allDocuments], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('获取文档数据失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '获取文档数据失败，请稍后重试', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
