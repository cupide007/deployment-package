<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

if (!isset($_FILES['document'])) {
    http_response_code(400);
    echo json_encode(['error' => 'no_file_uploaded']);
    exit;
}

if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMap = [
            UPLOAD_ERR_INI_SIZE => 'File too large (php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (html form)',
            UPLOAD_ERR_PARTIAL => 'Partial upload',
            UPLOAD_ERR_NO_FILE => 'No file',
            UPLOAD_ERR_NO_TMP_DIR => 'No tmp dir',
            UPLOAD_ERR_CANT_WRITE => 'Write failed',
            UPLOAD_ERR_EXTENSION => 'PHP extension error'
    ];
    $msg = $errorMap[$_FILES['document']['error']] ?? 'Unknown error';
    echo json_encode(['error' => 'upload_error', 'message' => $msg]);
    exit;
}

$allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'md', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif'];
$originalName = $_FILES['document']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_type']);
    exit;
}

try {
    if (!class_exists('Database')) {
    }
    $db = new Database('antister_virtual_country');

    $docId = md5(uniqid('doc', true) . mt_rand());

    $content = file_get_contents($_FILES['document']['tmp_name']);
    if ($content === false) {
        throw new Exception('Read file failed');
    }

    $base64 = base64_encode($content);

    $chunkSize = 50000;
    $chunks = str_split($base64, $chunkSize);
    $totalChunks = count($chunks);

    foreach ($chunks as $index => $chunk) {
        $key = "chunk_{$docId}_{$index}";
        $result = $db->set($key, $chunk);
        if ($result === false) {
            for ($j = 0; $j < $index; $j++) {
                $db->delete("chunk_{$docId}_{$j}");
            }
            throw new Exception("Save chunk $index failed");
        }
    }

    $docData = [
            'id' => $docId,
            'name' => $originalName,
            'type' => $_FILES['document']['type'],
            'size' => $_FILES['document']['size'],
            'chunk_count' => $totalChunks,
            'uploaded_at' => date('c')
    ];

    $metaKey = "meta_{$docId}";
    $metaResult = $db->set($metaKey, json_encode($docData));

    if ($metaResult === false) {
        for ($j = 0; $j < $totalChunks; $j++) {
            $db->delete("chunk_{$docId}_{$j}");
        }
        throw new Exception("Save meta failed");
    }

    $returnUrl = 'document-file.php?id=' . $docId . '&name=' . urlencode($originalName);

    echo json_encode(['url' => $returnUrl]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
            'error' => 'database_storage_failed',
            'message' => $e->getMessage()
    ]);
    exit;
}