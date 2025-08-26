<?php
/**
 * 特定ステーションの最新データ取得エンドポイント
 * 
 * URL: /latest.php?station_id=xxxxx
 * Method: GET
 */

require_once 'config.php';
require_once 'models.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    // パラメータ取得
    $stationId = $_GET['station_id'] ?? '';
    
    if (empty($stationId)) {
        sendError('station_id parameter required', 400);
    }
    
    $stationModel = new WeatherStation();
    $observationModel = new WeatherObservation();
    
    // ステーション存在チェック
    $station = $stationModel->getById($stationId);
    if (!$station) {
        sendError('Station not found', 404);
    }
    
    // 最新観測データ取得
    $latestObservation = $observationModel->getLatestByStation($stationId);
    
    if (!$latestObservation) {
        sendError('No observation data found for this station', 404);
    }
    
    sendJSON([
        'station' => [
            'station_id' => $station['station_id'],
            'name' => $station['name'],
            'model' => $station['model'],
            'stationtype' => $station['stationtype'],
            'location' => $station['location'],
            'latitude' => $station['latitude'] ? (float)$station['latitude'] : null,
            'longitude' => $station['longitude'] ? (float)$station['longitude'] : null
        ],
        'observation' => $latestObservation
    ]);
    
} catch (Exception $e) {
    logError("Error retrieving latest data", [
        'error' => $e->getMessage(),
        'station_id' => $stationId ?? 'unknown'
    ]);
    sendError('Failed to retrieve latest data', 500);
}
?>