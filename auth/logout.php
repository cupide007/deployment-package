<?php
require_once '../common.php';
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_COOKIE['sessionId'] ?? '';
if ($sessionId) {
    $db = new Database('retinbox-main');
    $db->delete('sess_' . $sessionId);
    setcookie('sessionId', '', time() - 3600, '/');
}
jsonResponse(['success' => true]);
?>