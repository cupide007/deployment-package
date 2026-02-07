<?php
require_once '../common.php';
$db = new Database('retinbox-main');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

// 需要 library 模块权限
requireModulePermission($db, 'library');
$data = getJsonInput();
$docs = $data['documents'] ?? [];
$key = "lib_documents_global";
$db->set($key, json_encode($docs));
jsonResponse(['success' => true, 'message' => '文档列表已更新']);
?>