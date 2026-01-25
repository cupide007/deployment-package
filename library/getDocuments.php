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

try {
    if (!class_exists('Database')) {
        throw new Exception('Database class not found');
    }
    $db = new Database('antister_virtual_country');
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $sessionId = $_COOKIE['sessionId'] ?? null;
        if (isset($_SERVER['HTTP_X_SESSION_ID'])) $sessionId = $_SERVER['HTTP_X_SESSION_ID'];

        $session = verifySession($db, $sessionId);
        if (!$session) {
            http_response_code(401);
            echo json_encode(['error' => 'auth_failed']);
            exit;
        }

        $safeUserId = md5($session['userId']);
        $raw = $db->get("documents_$safeUserId");

        $documents = [];

        if ($raw) {
            $meta = json_decode($raw, true);

            // 检查是否为分片存储格式
            if (is_array($meta) && isset($meta['type']) && $meta['type'] === 'chunked_list') {
                // 是分片数据，开始拼接
                $fullJson = '';
                for ($i = 0; $i < $meta['count']; $i++) {
                    $chunkKey = "docs_chunk_{$safeUserId}_{$i}";
                    $chunk = $db->get($chunkKey);
                    if ($chunk) {
                        $fullJson .= $chunk;
                    }
                }
                $documents = json_decode($fullJson, true);
            } else {
                // 兼容旧数据（非分片）
                $documents = $meta; // $meta 此时就是解析后的数组
            }
        }

        if (!is_array($documents)) {
            $documents = [];
        }

        http_response_code(200);
        echo json_encode(['documents' => $documents], JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}