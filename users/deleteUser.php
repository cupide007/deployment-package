<?php
// 删除用户云函数
header('Content-Type: application/json; charset=utf-8');
// 允许跨域
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, x-session-id");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 简单的 Session 验证逻辑
function verifySession($db, $sessionId) {
    if (!$sessionId) return null;
    $sessionData = $db->get("session_$sessionId");
    if (!$sessionData) return null;
    try {
        $session = json_decode($sessionData, true);
        if (empty($session['expiresAt']) || empty($session['userId'])) return null;
        if (new DateTime() > new DateTime($session['expiresAt'])) {
            $db->delete("session_$sessionId");
            return null;
        }
        return $session;
    } catch (Exception $e) { return null; }
}

try {
    $db = new Database('antister_virtual_country');

    // 1. 获取输入数据
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true) ?? [];

    $targetUserId = $jsonInput['id']
        ?? $jsonInput['userId']
        ?? $_POST['id']
        ?? $_POST['userId']
        ?? null;

    // 2. 权限验证
    $sessionId = $_COOKIE['sessionId'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? null;
    $session = verifySession($db, $sessionId);

    if (!$session) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $currentUserId = $session['userId'];
    $currentUserData = $db->get("user_$currentUserId");
    $currentUser = json_decode($currentUserData, true);

    if (!isset($currentUser['role']) || $currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '权限不足'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. 安全检查
    // 强制转换为字符串比较，防止类型不匹配
    if ((string)$targetUserId === (string)$currentUserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '不能删除当前登录的管理员账号'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. 执行删除

    // A. 更新索引列表
    $usersData = $db->get('users');
    $userIds = $usersData ? json_decode($usersData, true) : [];

    $targetUserIdStr = (string)$targetUserId;
    $foundInIndex = false;

    // 使用 array_values 重新索引
    $newUserIds = array_values(array_filter($userIds, function($id) use ($targetUserIdStr) {
        return (string)$id !== $targetUserIdStr;
    }));

    if (count($userIds) !== count($newUserIds)) {
        $foundInIndex = true;
        $db->set('users', json_encode($newUserIds));
    }

    // B. 删除关联数据
    $db->delete("user_$targetUserId");
    $db->delete("user_settings_$targetUserId");
    $db->delete("game_progress_$targetUserId");

    echo json_encode(['success' => true, 'message' => '用户删除成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '服务器错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>