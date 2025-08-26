<?php
/**
 * システム統計情報取得エンドポイント
 * 
 * URL: /stats.php
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
    $observationModel = new WeatherObservation();
    
    // システム統計情報取得
    $statistics = $observationModel->getStatistics();
    
    sendJSON($statistics);
    
} catch (Exception $e) {
    logError("Error retrieving statistics", ['error' => $e->getMessage()]);
    sendError('Failed to retrieve statistics', 500);
}
?>