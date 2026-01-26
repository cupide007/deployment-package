<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if ($sessionId) {
    $db->delete('sess_' . $sessionId);
}

setcookie('sessionId', '', time() - 3600, '/', '', true, true);
jsonResponse(['success' => true]);
?>