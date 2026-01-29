<?php
\RthRunner\Runtime::require_once_method('../common.php');
$db = new Database('retinbox-main');

$data = getJsonInput();

if (isset($data['log'])) {
    $data = $data['log'];
}

if (!$data || !is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'No data']);
    exit;
}

$logs = $db->get('sys_admin_logs');
$logsList = $logs ? json_decode($logs, true) : [];

if (!isset($data['id'])) $data['id'] = 'log_' . uniqid();
if (!isset($data['timestamp'])) $data['timestamp'] = date('Y-m-d H:i:s');

array_unshift($logsList, $data);

if (count($logsList) > 500) {
    $logsList = array_slice($logsList, 0, 500);
}

$db->set('sys_admin_logs', json_encode($logsList));

\RthRunner\header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>