<?php
require_once '../common.php';
$db = new Database('retinbox-main');
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false]);
    exit;
}

$logs = $db->get('admin_system_logs');
$logsList = $logs ? json_decode($logs, true) : [];

$data['id'] = 'log_' . uniqid();
$data['timestamp'] = date('c');
array_unshift($logsList, $data);

if (count($logsList) > 500) {
    $logsList = array_slice($logsList, 0, 500);
}

$db->set('admin_system_logs', json_encode($logsList));
echo json_encode(['success' => true]);
?>