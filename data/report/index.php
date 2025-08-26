<?php
/**
 * HP2550 気象データ受信エンドポイント（htdocs直下配置版）
 * 
 * URL: /data/report/
 * Method: POST
 * Content-Type: application/x-www-form-urlencoded
 * 
 * Ecowittデフォルトパス対応
 */

// デバッグ用: 複数のパス方式を試行
if (file_exists(__DIR__ . '/../../weather/config.php')) {
    require_once __DIR__ . '/../../weather/config.php';
    require_once __DIR__ . '/../../weather/models.php';
    require_once __DIR__ . '/../../weather/utils.php';
} else {
    // 別のパス構成の場合
    require_once '../weather/config.php';
    require_once '../weather/models.php';
    require_once '../weather/utils.php';
}

// CORS設定
setCORSHeaders();

// ヘルスチェックはここでは処理しない
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    sendError('This endpoint accepts POST requests only', 405);
}

// POST以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

try {
    // リクエストデータ取得
    $postData = RequestUtils::getPostData();
    
    if (!$postData) {
        sendError('No POST data received', 400);
    }
    
    $clientIP = RequestUtils::getClientIP();
    $userAgent = RequestUtils::getUserAgent();
    
    logInfo("Data received from HP2550 (root level)", [
        'client_ip' => $clientIP,
        'user_agent' => $userAgent,
        'data_keys' => array_keys($postData)
    ]);
    
    // PASSKEYチェック
    $passkey = $postData['PASSKEY'] ?? $postData['passkey'] ?? '';
    
    if (empty($passkey)) {
        logWarning("Missing PASSKEY", ['client_ip' => $clientIP]);
        sendError('PASSKEY required', 403);
    }
    
    // ステーション認証
    $stationModel = new WeatherStation();
    $station = $stationModel->findByPasskey($passkey);
    
    if (!$station) {
        logWarning("Invalid PASSKEY", [
            'client_ip' => $clientIP,
            'passkey_length' => strlen($passkey)
        ]);
        sendError('Invalid PASSKEY', 403);
    }
    
    logInfo("Station authenticated (root level)", [
        'station_id' => $station['station_id'],
        'station_name' => $station['name'],
        'client_ip' => $clientIP
    ]);
    
    // データ変換処理
    $dataProcessor = new EcowittDataProcessor();
    $processedData = $dataProcessor->processRawData($postData);
    
    if (empty($processedData['time_utc'])) {
        sendError('Invalid data: no timestamp found', 400);
    }
    
    // データベース保存
    $observationModel = new WeatherObservation();
    $success = $observationModel->saveObservation($station['station_id'], $processedData);
    
    if ($success) {
        logInfo("Data saved successfully (root level)", [
            'station_id' => $station['station_id'],
            'time_utc' => $processedData['time_utc'],
            'fields_count' => count($processedData)
        ]);
        
        sendJSON(ResponseUtils::success(null, 'Data received and saved successfully'));
    } else {
        sendError('Failed to save data', 500);
    }
    
} catch (PDOException $e) {
    logError("Database error in data reception (root level)", [
        'error' => $e->getMessage(),
        'client_ip' => $clientIP ?? 'unknown'
    ]);
    sendError('Database error', 500);
    
} catch (Exception $e) {
    logError("Unexpected error in data reception (root level)", [
        'error' => $e->getMessage(),
        'client_ip' => $clientIP ?? 'unknown'
    ]);
    sendError('Internal server error', 500);
}
?>