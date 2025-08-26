<?php
/**
 * HP2550 日別気象データ集計スクリプト
 * 
 * weather_observationテーブルから日別サマリーを作成し、
 * daily_weather_summaryテーブルに保存する。
 * 
 * 実行タイミング: 毎日 00:05
 * 処理対象: 前日まで未集計のデータ
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/daily_aggregation_functions.php';

// ログ設定
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/daily_aggregation.log');

$startTime = microtime(true);
$processedDates = 0;

echo "=========================================\n";
echo "HP2550 Daily Weather Aggregation\n";
echo "=========================================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "\n";

try {
    $pdo = getDBConnection();
    
    // 未集計の日付を取得
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            wo.station_id,
            DATE(wo.time_utc) as observation_date
        FROM weather_observation wo
        LEFT JOIN daily_weather_summary dws 
            ON wo.station_id = dws.station_id 
            AND DATE(wo.time_utc) = dws.observation_date
        WHERE dws.observation_date IS NULL
            AND DATE(wo.time_utc) < CURDATE()  -- 今日より前のデータのみ
        ORDER BY wo.station_id, observation_date
    ");
    
    $stmt->execute();
    $pendingDates = $stmt->fetchAll();
    
    if (empty($pendingDates)) {
        echo "✅ No pending dates found. All data is up to date.\n";
        exit(0);
    }
    
    echo "Found " . count($pendingDates) . " pending dates to process:\n";
    
    foreach ($pendingDates as $item) {
        echo "  {$item['station_id']}: {$item['observation_date']}\n";
    }
    echo "\n";
    
    // 各日付を処理
    foreach ($pendingDates as $item) {
        $stationId = $item['station_id'];
        $date = $item['observation_date'];
        
        echo "Processing {$stationId} - {$date}... ";
        
        try {
            $summary = calculateDailySummary($pdo, $stationId, $date);
            saveDailySummary($pdo, $stationId, $date, $summary);
            $processedDates++;
            echo "✅ Done\n";
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            error_log("Daily aggregation error for {$stationId} - {$date}: " . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    error_log("Daily aggregation fatal error: " . $e->getMessage());
    exit(1);
}

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n=========================================\n";
echo "Daily Aggregation Completed!\n";
echo "=========================================\n";
echo "Processed dates: $processedDates\n";
echo "Duration: {$duration} seconds\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
echo "=========================================\n";

?>