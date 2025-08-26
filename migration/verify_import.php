<?php
/**
 * CSV移行結果確認ツール
 */

require_once __DIR__ . '/../config.php';

if ($argc < 2) {
    echo "Usage: php verify_import.php <station_id>\n";
    echo "Example: php verify_import.php AMF_hp2550\n";
    exit(1);
}

$stationId = $argv[1];

echo "=========================================\n";
echo "Import Verification Tool\n";
echo "=========================================\n";
echo "Station ID: $stationId\n";
echo "\n";

try {
    $pdo = getDBConnection();
    
    // 1. 基本統計
    echo "1. Basic Statistics:\n";
    echo "----------------------------------------\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM weather_observation WHERE station_id = ?");
    $stmt->execute([$stationId]);
    $total = $stmt->fetch()['total'];
    echo "Total observations: " . number_format($total) . "\n";
    
    if ($total == 0) {
        echo "⚠️  No data found for station: $stationId\n";
        exit(1);
    }
    
    // 2. 期間統計
    echo "\n2. Date Range:\n";
    echo "----------------------------------------\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            MIN(time_utc) as earliest,
            MAX(time_utc) as latest,
            DATEDIFF(MAX(time_utc), MIN(time_utc)) as days
        FROM weather_observation
        WHERE station_id = ?
    ");
    $stmt->execute([$stationId]);
    $range = $stmt->fetch();
    
    echo "Earliest: {$range['earliest']}\n";
    echo "Latest: {$range['latest']}\n";
    echo "Total days: " . number_format($range['days']) . "\n";
    
    // 3. データ品質
    echo "\n3. Data Quality:\n";
    echo "----------------------------------------\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN temp_c IS NULL THEN 1 ELSE 0 END) as missing_temp,
            SUM(CASE WHEN humidity IS NULL THEN 1 ELSE 0 END) as missing_humidity,
            SUM(CASE WHEN pressure_hpa IS NULL THEN 1 ELSE 0 END) as missing_pressure,
            SUM(CASE WHEN temp_c < -50 OR temp_c > 80 THEN 1 ELSE 0 END) as invalid_temp,
            ROUND(MIN(temp_c), 1) as min_temp,
            ROUND(MAX(temp_c), 1) as max_temp,
            ROUND(AVG(temp_c), 1) as avg_temp
        FROM weather_observation 
        WHERE station_id = ?
    ");
    $stmt->execute([$stationId]);
    $quality = $stmt->fetch();
    
    echo "Missing temperature: " . number_format($quality['missing_temp']) . "\n";
    echo "Missing humidity: " . number_format($quality['missing_humidity']) . "\n";
    echo "Missing pressure: " . number_format($quality['missing_pressure']) . "\n";
    echo "Invalid temperatures: " . number_format($quality['invalid_temp']) . "\n";
    echo "Temperature range: {$quality['min_temp']}°C to {$quality['max_temp']}°C (avg: {$quality['avg_temp']}°C)\n";
    
    // 4. 年別データ数
    echo "\n4. Records by Year:\n";
    echo "----------------------------------------\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            YEAR(time_utc) as year,
            COUNT(*) as count,
            MIN(time_utc) as first_record,
            MAX(time_utc) as last_record
        FROM weather_observation 
        WHERE station_id = ?
        GROUP BY YEAR(time_utc) 
        ORDER BY year
    ");
    $stmt->execute([$stationId]);
    
    while ($row = $stmt->fetch()) {
        echo "  {$row['year']}: " . number_format($row['count']) . " records ({$row['first_record']} to {$row['last_record']})\n";
    }
    
    // 5. 最新サンプル
    echo "\n5. Latest 5 Records:\n";
    echo "----------------------------------------\n";
    
    $stmt = $pdo->prepare("
        SELECT time_utc, temp_c, humidity, pressure_hpa, wind_speed_ms, solar_wm2
        FROM weather_observation 
        WHERE station_id = ?
        ORDER BY time_utc DESC 
        LIMIT 5
    ");
    $stmt->execute([$stationId]);
    
    printf("%-19s | %8s | %8s | %10s | %9s | %9s\n", 
           "Time", "Temp(°C)", "Humid(%)", "Press(hPa)", "Wind(m/s)", "Solar(W/m²)");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $stmt->fetch()) {
        printf("%-19s | %8s | %8s | %10s | %9s | %9s\n",
            $row['time_utc'],
            $row['temp_c'] ?? 'NULL',
            $row['humidity'] ?? 'NULL',
            $row['pressure_hpa'] ?? 'NULL',
            $row['wind_speed_ms'] ?? 'NULL',
            $row['solar_wm2'] ?? 'NULL'
        );
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=========================================\n";
echo "Verification completed!\n";
echo "=========================================\n";

?>