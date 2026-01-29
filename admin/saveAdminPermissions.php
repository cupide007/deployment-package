<?php
\RthRunner\Runtime::require_once_method('../common.php');
$db = new Database('retinbox-main');

$params = [];
if (isset($_REQUEST['payload'])) {
    $payloadData = json_decode($_REQUEST['payload'], true);
    if (isset($payloadData['query']) && is_array($payloadData['query'])) {
        $params = array_merge($params, $payloadData['query']);
    }
}
if (empty($params) && (isset($_REQUEST['adminId']) || isset($_REQUEST['userId']))) {
    $params = array_merge($params, $_REQUEST);
}
if (empty($params)) {
    $rawInput = \RthRunner\file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (is_array($jsonInput)) {
        if (isset($jsonInput['query'])) {
            $params = array_merge($params, $jsonInput['query']);
        } else {
            $params = array_merge($params, $jsonInput);
        }
    }
}

if (empty($params) || (!isset($params['adminId']) && !isset($params['userId']))) {
    \RthRunner\header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "Data incomplete (missing adminId)."]);
    exit;
}

$targetId = $params['adminId'] ?? $params['userId'];
$username = $params['username'] ?? '';
$adminName = $params['adminName'] ?? '';
$level = $params['level'] ?? 'admin';


$modules = [];
if (isset($_SERVER['REQUEST_URI'])) {
    $queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);

    if ($queryString) {
        if (preg_match_all('/modules(?:%5B%5D|\[\])?=([^&]+)/i', $queryString, $matches)) {
            if (isset($matches[1]) && is_array($matches[1])) {
                foreach ($matches[1] as $val) {
                    $modules[] = urldecode($val);
                }
            }
        }
    }
}

if (empty($modules)) {
    if (isset($params['modules']) && is_array($params['modules'])) {
        $modules = $params['modules'];
    }
    elseif (isset($params['modules[]'])) {
        $val = $params['modules[]'];
        $modules = is_array($val) ? $val : explode(',', (string)$val);
    }
}

$modules = array_unique($modules);

$userRaw = $db->get($targetId);
if ($userRaw) {
    $userObj = json_decode($userRaw, true);
    if (($userObj['role'] ?? '') !== 'admin') {
        $userObj['role'] = 'admin';
        $db->set($targetId, json_encode($userObj));
    }
}

$permissionsRaw = $db->get('sys_admin_permissions');
$permissionsList = $permissionsRaw ? json_decode($permissionsRaw, true) : [];

$found = false;
foreach ($permissionsList as &$p) {
    if ($p['userId'] === $targetId) {
        $p['adminName'] = $adminName ?: ($p['adminName'] ?? $username);
        $p['level'] = $level;
        $p['modules'] = $modules;
        $found = true;
        break;
    }
}

if (!$found) {
    $newPerm = [
        'id' => 'perm_' . uniqid(),
        'userId' => $targetId,
        'username' => $username,
        'adminName' => $adminName ?: $username,
        'level' => $level,
        'modules' => $modules,
        'createdAt' => date('Y-m-d H:i:s')
    ];
    $permissionsList[] = $newPerm;
}

$db->set('sys_admin_permissions', json_encode($permissionsList));

\RthRunner\header('Content-Type: application/json');
echo json_encode(['success' => true, 'saved_modules' => $modules]);
exit;
?>