<?php
/**
 * ステーション一覧・情報取得エンドポイント
 * 
 * URL: /stations.php
 * Method: GET
 */
// システム定数定義（config.php保護用）
define('HP2550_SYSTEM', true);


require_once 'config.php';
require_once 'models.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $stationModel = new WeatherStation();
    $observationModel = new WeatherObservation();
    
    // アクティブなステーション取得
    $stations = $stationModel->getActiveStations();
    $stationData = [];
    
    foreach ($stations as $station) {
        $stationInfo = [
            'station_id' => $station['station_id'],
            'name' => $station['name'],
            'model' => $station['model'],
            'stationtype' => $station['stationtype'],
            'location' => $station['location'],
            'latitude' => $station['latitude'] ? (float)$station['latitude'] : null,
            'longitude' => $station['longitude'] ? (float)$station['longitude'] : null,
            'created_at' => $station['created_at']
        ];
        
        // 最新データの取得
        $latestData = $observationModel->getLatestByStation($station['station_id']);
        if ($latestData) {
            $stationInfo['last_data_time'] = $latestData['time_utc'];
            $stationInfo['last_data_fields'] = count(array_filter($latestData, function($v) { return $v !== null; }));
        } else {
            $stationInfo['last_data_time'] = null;
            $stationInfo['last_data_fields'] = 0;
        }
        
        // データ件数
        $stationInfo['total_observations'] = $observationModel->getDataCount($station['station_id']);
        
        $stationData[] = $stationInfo;
    }
    
    sendJSON([
        'stations' => $stationData,
        'total_count' => count($stationData)
    ]);
    
} catch (Exception $e) {
    logError("Error retrieving stations", ['error' => $e->getMessage()]);
    sendError('Failed to retrieve stations', 500);
}
?>