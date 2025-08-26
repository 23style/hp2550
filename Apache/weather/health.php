<?php
/**
 * ヘルスチェックエンドポイント
 * 
 * URL: /health.php
 * Method: GET
 */

require_once 'config.php';
require_once 'models.php';

setCORSHeaders();

try {
    // データベースヘルスチェック
    $dbHealth = checkDatabaseHealth();
    
    $response = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'system' => [
            'php_version' => PHP_VERSION,
            'timezone' => date_default_timezone_get(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ],
        'database' => $dbHealth
    ];
    
    if ($dbHealth['status'] !== 'ok') {
        http_response_code(503);
        $response['status'] = 'degraded';
    }
    
    sendJSON($response);
    
} catch (Exception $e) {
    logError("Health check failed", ['error' => $e->getMessage()]);
    
    sendJSON([
        'status' => 'error',
        'error' => 'Health check failed',
        'timestamp' => date('c')
    ], 503);
}
?>