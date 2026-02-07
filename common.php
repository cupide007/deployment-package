<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');
// 动态设置 Origin，支持跨域请求时携带 Cookie
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Id');
header("Content-Security-Policy: default-src 'self' https: data: blob: 'unsafe-inline' 'unsafe-eval'; img-src 'self' https: data: blob:; media-src 'self' https: data: blob:;");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $jsonData = json_decode($raw, true);

    $jsonData = is_array($jsonData) ? $jsonData : [];

    $postData = is_array($_POST) ? $_POST : [];

    return array_merge($postData, $jsonData);
}

/**
 * 获取当前登录用户信息
 * @param Database $db 数据库实例
 * @return array|null 用户信息数组或 null
 */
function getCurrentUser($db) {
    $sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
    if (!$sessionId) return null;
    
    $sessionData = $db->get('sess_' . $sessionId);
    if (!$sessionData) return null;
    
    $userId = json_decode($sessionData, true)['userId'];
    $userRaw = $db->get($userId);
    if (!$userRaw) return null;
    
    $user = json_decode($userRaw, true);
    $user['_sessionId'] = $sessionId;
    return $user;
}

/**
 * 检查用户是否有指定模块的访问权限
 * @param Database $db 数据库实例
 * @param string $userId 用户ID
 * @param string $module 模块名称 (dashboard, users, bank, library, permissions)
 * @return bool 是否有权限
 */
function hasModulePermission($db, $userId, $module) {
    // 获取用户信息
    $userRaw = $db->get($userId);
    if (!$userRaw) return false;
    
    $user = json_decode($userRaw, true);
    
    // 不是管理员直接返回 false
    if (($user['role'] ?? '') !== 'admin') {
        return false;
    }
    
    // 获取权限列表
    $permissionsRaw = $db->get('sys_admin_permissions');
    $permissions = $permissionsRaw ? json_decode($permissionsRaw, true) : [];
    
    // 查找该用户的权限配置
    foreach ($permissions as $perm) {
        if ($perm['userId'] === $userId) {
            $modules = $perm['modules'] ?? [];
            return in_array($module, $modules);
        }
    }
    
    // 如果是管理员但没有权限配置，默认拥有所有权限（向后兼容）
    return true;
}

/**
 * 确保用户已登录且是管理员，并拥有指定模块权限
 * @param Database $db 数据库实例
 * @param string $module 模块名称
 * @return array 当前用户信息
 */
function requireModulePermission($db, $module) {
    $user = getCurrentUser($db);
    if (!$user) {
        jsonError('未登录', 401);
    }
    
    if (($user['role'] ?? '') !== 'admin') {
        jsonError('无权访问，仅管理员可操作', 403);
    }
    
    if (!hasModulePermission($db, $user['id'], $module)) {
        jsonError("无权访问 {$module} 模块", 403);
    }
    
    return $user;
}

if (!class_exists('Database')) {
    jsonError('严重错误：未找到平台数据库类，请在热铁盒环境中运行。', 500);
}