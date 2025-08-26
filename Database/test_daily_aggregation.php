<?php
/**
 * 日別集計テストスクリプト
 * 指定した特定日のサマリーのみを手動作成してテスト
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/daily_aggregation_functions.php';  // 関数のみを読み込み

echo "=========================================\n";
echo "HP2550 Daily Aggregation Test Tool\n";
echo "=========================================\n";

if ($argc < 2) {
    echo "Usage: php test_daily_aggregation.php <date> [station_id]\n";
    echo "Example: php test_daily_aggregation.php 2024-12-23\n";
    echo "Example: php test_daily_aggregation.php 2024-12-23 AMF_hp2550\n";
    exit(1);
}

$testDate = $argv[1];
$stationId = $argv[2] ?? 'AMF_hp2550';

// 日付形式チェック
if (!DateTime::createFromFormat('Y-m-d', $testDate)) {
    echo "❌ Invalid date format. Use YYYY-MM-DD\n";
    exit(1);
}

echo "Test Date: $testDate\n";
echo "Station ID: $stationId\n";
echo "\n";

try {
    $pdo = getDBConnection();
    
    // 対象日のデータ存在確認
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, 
               MIN(time_utc) as earliest, 
               MAX(time_utc) as latest
        FROM weather_observation 
        WHERE station_id = ? AND DATE(time_utc) = ?
    ");
    $stmt->execute([$stationId, $testDate]);
    $dataInfo = $stmt->fetch();
    
    if ($dataInfo['count'] == 0) {
        echo "❌ No observation data found for {$testDate}\n";
        
        // 利用可能な日付を表示
        echo "\nAvailable recent dates:\n";
        $stmt = $pdo->prepare("
            SELECT DISTINCT DATE(time_utc) as date, COUNT(*) as count
            FROM weather_observation 
            WHERE station_id = ?
            ORDER BY date DESC 
            LIMIT 10
        ");
        $stmt->execute([$stationId]);
        
        while ($row = $stmt->fetch()) {
            echo "  {$row['date']} ({$row['count']} records)\n";
        }
        exit(1);
    }
    
    echo "Found {$dataInfo['count']} observation records for {$testDate}\n";
    echo "Time range: {$dataInfo['earliest']} to {$dataInfo['latest']}\n";
    echo "\n";
    
    // 既存サマリーチェック
    $stmt = $pdo->prepare("
        SELECT * FROM daily_weather_summary 
        WHERE station_id = ? AND observation_date = ?
    ");
    $stmt->execute([$stationId, $testDate]);
    $existingSummary = $stmt->fetch();
    
    if ($existingSummary) {
        echo "⚠️  Existing summary found for {$testDate}. It will be overwritten.\n";
        echo "\n";
    }
    
    echo "Calculating daily summary for {$testDate} only...\n";
    
    // サマリー計算（指定日のみ）
    $summary = calculateDailySummary($pdo, $stationId, $testDate);
    
    echo "✅ Summary calculated for {$testDate}. Key metrics:\n";
    echo "  Temperature: {$summary['temp_min']}°C - {$summary['temp_max']}°C (avg: {$summary['temp_avg']}°C)\n";
    echo "  Humidity: {$summary['humidity_min']}% - {$summary['humidity_max']}% (avg: {$summary['humidity_avg']}%)\n";
    echo "  Pressure: {$summary['pressure_sea_min']}hPa - {$summary['pressure_sea_max']}hPa (avg: {$summary['pressure_sea_avg']}hPa)\n";
    echo "  Wind: max {$summary['wind_max_speed']}m/s, gust {$summary['wind_max_gust']}m/s (avg: {$summary['wind_avg_speed']}m/s)\n";
    echo "  Precipitation: {$summary['precipitation_total']}mm\n";
    echo "  Solar radiation: {$summary['solar_radiation']}MJ/m²\n";
    echo "  UV Index max: {$summary['uv_index_max']}\n";
    
    // 特定時刻データ表示
    echo "\n  Specific times:\n";
    echo "    09:00 - Temp: {$summary['temp_09']}°C, Humidity: {$summary['humidity_09']}%, Pressure: {$summary['pressure_sea_09']}hPa\n";
    echo "    15:00 - Temp: {$summary['temp_15']}°C, Humidity: {$summary['humidity_15']}%, Pressure: {$summary['pressure_sea_15']}hPa\n";
    echo "    21:00 - Temp: {$summary['temp_21']}°C, Humidity: {$summary['humidity_21']}%, Pressure: {$summary['pressure_sea_21']}hPa\n";
    echo "\n";
    
    // 確認プロンプト
    echo "Save this summary to database? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $confirmation = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($confirmation) !== 'y') {
        echo "Operation cancelled. No data was saved.\n";
        exit(0);
    }
    
    // サマリー保存
    saveDailySummary($pdo, $stationId, $testDate, $summary);
    
    echo "✅ Daily summary for {$testDate} saved successfully!\n";
    echo "\n";
    
    // 保存結果確認
    $stmt = $pdo->prepare("
        SELECT created_at, updated_at FROM daily_weather_summary 
        WHERE station_id = ? AND observation_date = ?
    ");
    $stmt->execute([$stationId, $testDate]);
    $savedSummary = $stmt->fetch();
    
    if ($savedSummary) {
        echo "Verification - Saved summary:\n";
        echo "  Date processed: {$testDate}\n";
        echo "  Station: {$stationId}\n";
        echo "  Created: {$savedSummary['created_at']}\n";
        echo "  Updated: {$savedSummary['updated_at']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=========================================\n";
echo "Test completed successfully!\n";
echo "Only processed: {$testDate}\n";
echo "=========================================\n";

?>