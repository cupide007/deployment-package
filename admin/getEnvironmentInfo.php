<?php
/**
 * 获取环境信息的云函数
 * 用于响应前端环境管理界面的请求
 */
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database('antister_virtual_country');
    
    // 获取环境信息报告
    $report = [
        'timestamp' => date('c'),
        'platform' => 'Retinbox',
        'runtime' => 'PHP',
        'phpVersion' => phpversion(),
        'serverInfo' => [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Retinbox Cloud',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
            'requestMethod' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ],
        'database' => [
            'type' => 'Retinbox Database',
            'status' => 'connected',
            'name' => 'antister_virtual_country'
        ],
        'memory' => [
            'limit' => ini_get('memory_limit'),
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true)
        ],
        'extensions' => [
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'date' => extension_loaded('date')
        ],
        'status' => 'healthy'
    ];
    
    // 返回成功响应
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $report,
        'message' => '环境检测成功'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('环境检测失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '执行环境检测时出错',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
