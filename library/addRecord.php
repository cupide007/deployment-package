<?php
require_once '../common.php';

$db = new Database('retinbox-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('请求方法不允许', 405);
}

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if (!$sessionId) jsonError('未登录', 401);
$sessionData = $db->get('sess_' . $sessionId);
if (!$sessionData) jsonError('会话已过期', 401);
$userId = json_decode($sessionData, true)['userId'];

$data = getJsonInput();
$bookTitle = $data['bookTitle'] ?? '未知图书';
$bookId = $data['bookId'] ?? uniqid();

$record = [
    'id' => uniqid('rec_'),
    'userId' => $userId,
    'bookId' => $bookId,
    'bookTitle' => $bookTitle,
    'borrowDate' => date('c'),
    'status' => 'borrowed'
];

$listKey = "lib_records_" . $userId;
$listRaw = $db->get($listKey);
$list = $listRaw ? json_decode($listRaw, true) : [];
array_unshift($list, $record);
$db->set($listKey, json_encode($list));

jsonResponse(['success' => true, 'record' => $record]);
?>