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
    if ($method === 'POST') {
        $sessionId = $_COOKIE['sessionId'] ?? null;
        if (isset($_SERVER['HTTP_X_SESSION_ID'])) $sessionId = $_SERVER['HTTP_X_SESSION_ID'];
        $session = verifySession($db, $sessionId);
        if (!$session) {
            http_response_code(401);
            echo json_encode(['error' => 'auth_failed']);
            exit;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $documents = $data['documents'] ?? null;
        if ($documents === null && isset($_POST['documents'])) {
            $documents = $_POST['documents'];
        }
        if (is_string($documents)) {
            header('Content-Type: application/json; charset=utf-8');

            function verifySession($db, $sessionId)
            {
                if (!$sessionId) return null;
                $sessionData = $db->get("session_$sessionId");
                if (!$sessionData) return null;
                try {
                    $session = json_decode($sessionData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) return null;
                } catch (Exception $e) {
                    return null;
                }
                if (empty($session['expiresAt']) || empty($session['userId'])) return null;
                $now = new DateTime();
                try {
                    $expiresAt = new DateTime($session['expiresAt']);
                } catch (Exception $e) {
                    $db->delete("session_$sessionId");
                    return null;
                }
                if ($now > $expiresAt) {
                    $db->delete("session_$sessionId");
                    return null;
                }
                return $session;
            }

            try {
                if (!class_exists('Database')) {
                    throw new Exception('Database class not found');
                }
                $db = new Database('antister_virtual_country');
                $method = $_SERVER['REQUEST_METHOD'];

                if ($method === 'POST') {
                    $sessionId = $_COOKIE['sessionId'] ?? null;
                    if (isset($_SERVER['HTTP_X_SESSION_ID'])) $sessionId = $_SERVER['HTTP_X_SESSION_ID'];

                    $session = verifySession($db, $sessionId);
                    if (!$session) {
                        http_response_code(401);
                        echo json_encode(['error' => 'auth_failed']);
                        exit;
                    }

                    // 获取并解析输入
                    $input = file_get_contents('php://input');
                    $data = json_decode($input, true);
                    $documents = $data['documents'] ?? null;

                    if ($documents === null && isset($_POST['documents'])) {
                        $documents = $_POST['documents'];
                    }

                    // 确保 documents 是数组
                    if (is_string($documents)) {
                        $decoded = json_decode($documents, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $documents = $decoded;
                        }
                    }
                    if (!is_array($documents)) {
                        $documents = $documents === null ? [] : [$documents];
                    }

                    // 【关键修复】检测并清理可能混入的超大 Base64 数据
                    // 防止某条坏数据导致 JSON 膨胀
                    foreach ($documents as $key => $doc) {
                        if (isset($doc['fileUrl']) && strlen($doc['fileUrl']) > 5000) {
                            // 如果 URL 超过 5000 字符，说明是 Base64，将其重置或标记
                            $documents[$key]['fileUrl'] = '#error_file_too_large';
                            $documents[$key]['description'] = '(系统自动清理：原文件数据过大，请重新上传)';
                        }
                    }

                    $safeUserId = md5($session['userId']);
                    $jsonString = json_encode($documents, JSON_UNESCAPED_UNICODE);

                    // 分片处理
                    $chunkSize = 60000; // 保守设置为 60KB (略小于 65535)
                    $chunks = str_split($jsonString, $chunkSize);
                    $totalChunks = count($chunks);

                    // 1. 存分片
                    foreach ($chunks as $index => $chunk) {
                        $key = "docs_chunk_{$safeUserId}_{$index}";
                        $db->set($key, $chunk);
                    }

                    // 2. 存元数据（包含分片数量）
                    $metaData = [
                        'type' => 'chunked_list',
                        'count' => $totalChunks,
                        'updated_at' => time()
                    ];
                    $db->set("documents_$safeUserId", json_encode($metaData));

                    http_response_code(200);
                    echo json_encode(['message' => 'success', 'count' => count($documents)], JSON_UNESCAPED_UNICODE);

                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'method_not_allowed']);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            $decodedDocuments = json_decode($documents, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $documents = $decodedDocuments;
            }
        }
        if (!is_array($documents)) {
            $documents = $documents === null ? [] : [$documents];
        }
        $safeUserId = md5($session['userId']);
        $db->set("documents_$safeUserId", json_encode($documents));
        http_response_code(200);
        echo json_encode(['message' => 'success', 'documents' => $documents], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}