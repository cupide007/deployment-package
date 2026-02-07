<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method Not Allowed', 405);

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

$data = getJsonInput();
$recordId = $data['recordId'] ?? '';

$listKey = "lib_records_" . $userId;
$listRaw = $db->get($listKey);
$list = $listRaw ? json_decode($listRaw, true) : [];

$found = false;
foreach ($list as &$record) {
    if ($record['id'] === $recordId && $record['status'] === 'borrowed') {
        $record['status'] = 'returned';
        $record['returnDate'] = date('c');
        $found = true;
        break;
    }
}

if ($found) {
    $db->set($listKey, json_encode($list));
    jsonResponse(['success' => true, 'message' => '归还成功']);
} else {
    jsonError('记录不存在或已归还');
}
?>