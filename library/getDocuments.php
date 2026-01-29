<?php
require_once '../common.php';

$db = new Database('retinbox-main');

$listRaw = $db->get("lib_documents_global");
$documents = $listRaw ? json_decode($listRaw, true) : [];

$validDocs = [];
foreach ($documents as $doc) {
    if (!empty($doc['approved'])) {
        $validDocs[] = $doc;
    }
}

jsonResponse(['success' => true, 'documents' => $validDocs]);
?>