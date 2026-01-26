<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$listRaw = $db->get("lib_documents_global");
$documents = $listRaw ? json_decode($listRaw, true) : [];

jsonResponse(['success' => true, 'documents' => $documents]);
?>