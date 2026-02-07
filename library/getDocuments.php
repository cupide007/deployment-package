<?php
require_once '../common.php';

$db = new Database('retinbox-main');

// 检查是否为管理员
$isAdmin = false;
$showAll = isset($_GET['showAll']) && $_GET['showAll'] === 'true';
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if ($sessionId) {
    $sessionData = $db->get('sess_' . $sessionId);
    if ($sessionData) {
        $userId = json_decode($sessionData, true)['userId'];
        $userRaw = $db->get($userId);
        if ($userRaw) {
            $user = json_decode($userRaw, true);
            $isAdmin = ($user['role'] ?? '') === 'admin';
        }
    }
}

$listRaw = $db->get("lib_documents_global");
$documents = $listRaw ? json_decode($listRaw, true) : [];

// 管理员可以看到所有文献，普通用户只能看到已审核的
if ($isAdmin && $showAll) {
    // 管理员请求查看全部，返回所有文献
    $validDocs = $documents;
} else {
    // 普通用户或管理员不带 showAll 参数，只返回已审核的
    $validDocs = [];
    foreach ($documents as $doc) {
        if (!empty($doc['approved'])) {
            $validDocs[] = $doc;
        }
    }
}

jsonResponse(['success' => true, 'documents' => $validDocs, 'isAdmin' => $isAdmin]);
?>