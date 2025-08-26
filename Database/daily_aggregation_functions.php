<?php
/**
 * 日別集計関数ライブラリ
 * メイン処理ロジックを含まない純粋な関数のみ
 * Phase 3: エラーハンドリング強化版
 */

require_once __DIR__ . '/../config.php';

/**
 * 指定日の日別サマリーを計算
 */
function calculateDailySummary($pdo, $stationId, $date) {
    $summary = [];
    
    try {
        logInfo("Starting daily summary calculation", ['station_id' => $stationId, 'date' => $date]);
        
        // 基本統計クエリ
        $sql_basic_stats = "
            SELECT 
                -- 気温統計
                ROUND(AVG(temp_c), 1) as temp_avg,
                ROUND(MAX(temp_c), 1) as temp_max,
                ROUND(MIN(temp_c), 1) as temp_min,
                
                -- 湿度統計
                ROUND(AVG(humidity), 1) as humidity_avg,
                MAX(humidity) as humidity_max,
                MIN(humidity) as humidity_min,
                
                -- 気圧統計
                ROUND(AVG(pressure_hpa), 1) as pressure_sea_avg,
                ROUND(MAX(pressure_hpa), 1) as pressure_sea_max,
                ROUND(MIN(pressure_hpa), 1) as pressure_sea_min,
                
                -- 風速統計
                ROUND(AVG(wind_speed_ms), 1) as wind_avg_speed,
                ROUND(MAX(wind_speed_ms), 1) as wind_max_speed,
                ROUND(MAX(wind_gust_ms), 1) as wind_max_gust,
                
                -- 降水量統計
                ROUND(MAX(rain_day_mm), 1) as precipitation_total,
                
                -- 日射・UV統計
                ROUND(MAX(uv_index), 1) as uv_index_max,
                ROUND(AVG(uv_index), 1) as uv_index_avg,
                ROUND(SUM(solar_wm2 * 0.0036), 1) as solar_radiation,  -- W/m²を3分間隔でMJ/m²に変換
                ROUND(SUM(CASE WHEN solar_wm2 >= 150 THEN 0.05 ELSE 0 END), 1) as sunshine_hours
                
            FROM weather_observation 
            WHERE station_id = ? 
                AND DATE(time_utc) = ?
                AND temp_c IS NOT NULL
        ";
        
        try {
            $stmt = $pdo->prepare($sql_basic_stats);
            $stmt->execute([$stationId, $date]);
            $stats = $stmt->fetch();
            
            if (!$stats) {
                throw new Exception("No valid data found for {$date}");
            }
            
            $summary = array_merge($summary, $stats);
            logInfo("Basic statistics calculated successfully", ['records_found' => true]);
            
        } catch (PDOException $e) {
            logError("SQL Error in basic statistics query", [
                'sql' => 'basic_stats_query',
                'error' => $e->getMessage(),
                'station_id' => $stationId,
                'date' => $date
            ]);
            throw new Exception("Failed to calculate basic statistics: " . $e->getMessage());
        }
    
        // 最高気温とその時刻
        $sql_temp_max_time = "
            SELECT TIME(time_utc) as time_only
            FROM weather_observation 
            WHERE station_id = ? 
                AND DATE(time_utc) = ?
                AND temp_c = ?
            ORDER BY time_utc
            LIMIT 1
        ";
        
        try {
            $stmt = $pdo->prepare($sql_temp_max_time);
            $stmt->execute([$stationId, $date, $stats['temp_max']]);
            $result = $stmt->fetch();
            $summary['temp_max_time'] = $result ? $result['time_only'] : null;
            
        } catch (PDOException $e) {
            logError("SQL Error in temp max time query", [
                'sql' => 'temp_max_time_query',
                'error' => $e->getMessage(),
                'station_id' => $stationId,
                'date' => $date,
                'temp_max' => $stats['temp_max']
            ]);
            $summary['temp_max_time'] = null;
        }
        
        // 最低気温とその時刻
        try {
            $stmt->execute([$stationId, $date, $stats['temp_min']]);
            $result = $stmt->fetch();
            $summary['temp_min_time'] = $result ? $result['time_only'] : null;
            
        } catch (PDOException $e) {
            logError("SQL Error in temp min time query", [
                'sql' => 'temp_min_time_query',
                'error' => $e->getMessage(),
                'station_id' => $stationId,
                'date' => $date,
                'temp_min' => $stats['temp_min']
            ]);
            $summary['temp_min_time'] = null;
        }
    
    // 特定時刻データ取得処理は削除（カラム削除により不要）
    
        // 最大湿度とその時刻
        if ($stats['humidity_max']) {
            $sql_humidity_time = "
                SELECT TIME(time_utc) as time_only
                FROM weather_observation 
                WHERE station_id = ? 
                    AND DATE(time_utc) = ?
                    AND humidity = ?
                ORDER BY time_utc
                LIMIT 1
            ";
            
            try {
                $stmt = $pdo->prepare($sql_humidity_time);
                $stmt->execute([$stationId, $date, $stats['humidity_max']]);
                $result = $stmt->fetch();
                $summary['humidity_max_time'] = $result ? $result['time_only'] : null;
                
            } catch (PDOException $e) {
                logError("SQL Error in humidity max time query", [
                    'sql' => 'humidity_max_time_query',
                    'error' => $e->getMessage(),
                    'station_id' => $stationId,
                    'date' => $date,
                    'humidity_max' => $stats['humidity_max']
                ]);
                $summary['humidity_max_time'] = null;
            }
            
            // 最小湿度とその時刻
            try {
                $stmt->execute([$stationId, $date, $stats['humidity_min']]);
                $result = $stmt->fetch();
                $summary['humidity_min_time'] = $result ? $result['time_only'] : null;
                
            } catch (PDOException $e) {
                logError("SQL Error in humidity min time query", [
                    'sql' => 'humidity_min_time_query',
                    'error' => $e->getMessage(),
                    'station_id' => $stationId,
                    'date' => $date,
                    'humidity_min' => $stats['humidity_min']
                ]);
                $summary['humidity_min_time'] = null;
            }
        }
    
        // 最大気圧とその時刻
        if ($stats['pressure_sea_max']) {
            $sql_pressure_time = "
                SELECT TIME(time_utc) as time_only
                FROM weather_observation 
                WHERE station_id = ? 
                    AND DATE(time_utc) = ?
                    AND pressure_hpa = ?
                ORDER BY time_utc
                LIMIT 1
            ";
            
            try {
                $stmt = $pdo->prepare($sql_pressure_time);
                $stmt->execute([$stationId, $date, $stats['pressure_sea_max']]);
                $result = $stmt->fetch();
                $summary['pressure_sea_max_time'] = $result ? $result['time_only'] : null;
                
            } catch (PDOException $e) {
                logError("SQL Error in pressure max time query", [
                    'sql' => 'pressure_max_time_query',
                    'error' => $e->getMessage(),
                    'station_id' => $stationId,
                    'date' => $date,
                    'pressure_max' => $stats['pressure_sea_max']
                ]);
                $summary['pressure_sea_max_time'] = null;
            }
            
            // 最小気圧とその時刻
            try {
                $stmt->execute([$stationId, $date, $stats['pressure_sea_min']]);
                $result = $stmt->fetch();
                $summary['pressure_sea_min_time'] = $result ? $result['time_only'] : null;
                
            } catch (PDOException $e) {
                logError("SQL Error in pressure min time query", [
                    'sql' => 'pressure_min_time_query',
                    'error' => $e->getMessage(),
                    'station_id' => $stationId,
                    'date' => $date,
                    'pressure_min' => $stats['pressure_sea_min']
                ]);
                $summary['pressure_sea_min_time'] = null;
            }
        }
    
        // 最大風速とその時刻・風向
        if ($stats['wind_max_speed'] && $stats['wind_max_speed'] > 0) {
            $sql_wind_max = "
                SELECT TIME(time_utc) as time_only, wind_dir_deg
                FROM weather_observation 
                WHERE station_id = ? 
                    AND DATE(time_utc) = ?
                    AND wind_speed_ms = ?
                ORDER BY time_utc
                LIMIT 1
            ";
            
            try {
                $stmt = $pdo->prepare($sql_wind_max);
                $stmt->execute([$stationId, $date, $stats['wind_max_speed']]);
                $result = $stmt->fetch();
                if ($result) {
                    $summary['wind_max_time'] = $result['time_only'];
                    $summary['wind_max_direction'] = $result['wind_dir_deg'];
                }
                
            } catch (PDOException $e) {
                logError("SQL Error in wind max query", [
                    'sql' => 'wind_max_query',
                    'error' => $e->getMessage(),
                    'station_id' => $stationId,
                    'date' => $date,
                    'wind_max_speed' => $stats['wind_max_speed']
                ]);
                $summary['wind_max_time'] = null;
                $summary['wind_max_direction'] = null;
            }
        }
    
        // 最大瞬間風速とその時刻・風向
        if ($stats['wind_max_gust'] && $stats['wind_max_gust'] > 0) {
            $sql_wind_gust = "
                SELECT TIME(time_utc) as time_only, wind_dir_deg
                FROM weather_observation 
                WHERE station_id = ? 
                    AND DATE(time_utc) = ?
                    AND wind_gust_ms = ?
                ORDER BY time_utc
                LIMIT 1
            ";
            
            try {
                $stmt = $pdo->prepare($sql_wind_gust);
                $stmt->execute([$stationId, $date, $stats['wind_max_gust']]);
                $result = $stmt->fetch();
                if ($result) {
                    $summary['wind_max_gust_time'] = $result['time_only'];
                    $summary['wind_max_gust_direction'] = $result['wind_dir_deg'];
                }
                
            } catch (PDOException $e) {
                logError("SQL Error in wind gust max query", [
                    'sql' => 'wind_gust_max_query',
                    'error' => $e->getMessage(),
                    'station_id' => $stationId,
                    'date' => $date,
                    'wind_max_gust' => $stats['wind_max_gust']
                ]);
                $summary['wind_max_gust_time'] = null;
                $summary['wind_max_gust_direction'] = null;
            }
        }
    
        // 最大UVインデックスとその時刻
        if ($stats['uv_index_max'] && $stats['uv_index_max'] > 0) {
            $sql_uv_max_time = "
                SELECT TIME(time_utc) as time_only
                FROM weather_observation 
                WHERE station_id = ? 
                    AND DATE(time_utc) = ?
                    AND uv_index = ?
                ORDER BY time_utc
                LIMIT 1
            ";
            
            try {
                $stmt = $pdo->prepare($sql_uv_max_time);
                $stmt->execute([$stationId, $date, $stats['uv_index_max']]);
                $result = $stmt->fetch();
                $summary['uv_index_max_time'] = $result ? $result['time_only'] : null;
                
            } catch (PDOException $e) {
                logError("SQL Error in UV max time query", [
                    'sql' => 'uv_max_time_query',
                    'error' => $e->getMessage(),
                    'station_id' => $stationId,
                    'date' => $date,
                    'uv_index_max' => $stats['uv_index_max']
                ]);
                $summary['uv_index_max_time'] = null;
            }
        }
    
        // 1時間最大降水量の計算
        $sql_precipitation_max = "
            SELECT MAX(rain_hour_mm) as max_hourly_rain
            FROM weather_observation 
            WHERE station_id = ? 
                AND DATE(time_utc) = ?
                AND rain_hour_mm IS NOT NULL
        ";
        
        try {
            $stmt = $pdo->prepare($sql_precipitation_max);
            $stmt->execute([$stationId, $date]);
            $result = $stmt->fetch();
            $summary['precipitation_max_1h'] = $result ? $result['max_hourly_rain'] : null;
            
        } catch (PDOException $e) {
            logError("SQL Error in precipitation max query", [
                'sql' => 'precipitation_max_query',
                'error' => $e->getMessage(),
                'station_id' => $stationId,
                'date' => $date
            ]);
            $summary['precipitation_max_1h'] = null;
        }
        
        logInfo("Daily summary calculation completed", ['station_id' => $stationId, 'date' => $date]);
        return $summary;
        
    } catch (Exception $e) {
        logError("Failed to calculate daily summary", [
            'station_id' => $stationId,
            'date' => $date,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * 日別サマリーをデータベースに保存
 */
function saveDailySummary($pdo, $stationId, $date, $summary) {
    try {
        logInfo("Starting daily summary save", ['station_id' => $stationId, 'date' => $date]);
        
        $sql_insert = "
        INSERT INTO daily_weather_summary (
            station_id, observation_date,
            temp_avg, temp_max, temp_max_time, temp_min, temp_min_time,
            precipitation_total, precipitation_max_1h,
            wind_max_speed, wind_max_direction, wind_max_time,
            wind_max_gust, wind_max_gust_direction, wind_max_gust_time,
            wind_avg_speed,
            humidity_avg, humidity_max, humidity_max_time,
            humidity_min, humidity_min_time,
            pressure_sea_avg, pressure_sea_max, pressure_sea_max_time,
            pressure_sea_min, pressure_sea_min_time,
            sunshine_hours, solar_radiation, uv_index_avg, uv_index_max, uv_index_max_time,
            created_at, updated_at
        ) VALUES (
            :station_id, :observation_date,
            :temp_avg, :temp_max, :temp_max_time, :temp_min, :temp_min_time,
            :precipitation_total, :precipitation_max_1h,
            :wind_max_speed, :wind_max_direction, :wind_max_time,
            :wind_max_gust, :wind_max_gust_direction, :wind_max_gust_time,
            :wind_avg_speed,
            :humidity_avg, :humidity_max, :humidity_max_time,
            :humidity_min, :humidity_min_time,
            :pressure_sea_avg, :pressure_sea_max, :pressure_sea_max_time,
            :pressure_sea_min, :pressure_sea_min_time,
            :sunshine_hours, :solar_radiation, :uv_index_avg, :uv_index_max, :uv_index_max_time,
            NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            temp_avg = VALUES(temp_avg),
            temp_max = VALUES(temp_max),
            temp_max_time = VALUES(temp_max_time),
            temp_min = VALUES(temp_min),
            temp_min_time = VALUES(temp_min_time),
            precipitation_total = VALUES(precipitation_total),
            precipitation_max_1h = VALUES(precipitation_max_1h),
            wind_max_speed = VALUES(wind_max_speed),
            wind_max_direction = VALUES(wind_max_direction),
            wind_max_time = VALUES(wind_max_time),
            wind_max_gust = VALUES(wind_max_gust),
            wind_max_gust_direction = VALUES(wind_max_gust_direction),
            wind_max_gust_time = VALUES(wind_max_gust_time),
            wind_avg_speed = VALUES(wind_avg_speed),
            humidity_avg = VALUES(humidity_avg),
            humidity_max = VALUES(humidity_max),
            humidity_max_time = VALUES(humidity_max_time),
            humidity_min = VALUES(humidity_min),
            humidity_min_time = VALUES(humidity_min_time),
            pressure_sea_avg = VALUES(pressure_sea_avg),
            pressure_sea_max = VALUES(pressure_sea_max),
            pressure_sea_max_time = VALUES(pressure_sea_max_time),
            pressure_sea_min = VALUES(pressure_sea_min),
            pressure_sea_min_time = VALUES(pressure_sea_min_time),
            sunshine_hours = VALUES(sunshine_hours),
            solar_radiation = VALUES(solar_radiation),
            uv_index_avg = VALUES(uv_index_avg),
            uv_index_max = VALUES(uv_index_max),
            uv_index_max_time = VALUES(uv_index_max_time),
            updated_at = NOW()
        ";
        
        try {
            $stmt = $pdo->prepare($sql_insert);
            
            $params = [
                'station_id' => $stationId,
                'observation_date' => $date,
                'temp_avg' => $summary['temp_avg'] ?? null,
                'temp_max' => $summary['temp_max'] ?? null,
                'temp_max_time' => $summary['temp_max_time'] ?? null,
                'temp_min' => $summary['temp_min'] ?? null,
                'temp_min_time' => $summary['temp_min_time'] ?? null,
                'precipitation_total' => $summary['precipitation_total'] ?? null,
                'precipitation_max_1h' => $summary['precipitation_max_1h'] ?? null,
                'wind_max_speed' => $summary['wind_max_speed'] ?? null,
                'wind_max_direction' => $summary['wind_max_direction'] ?? null,
                'wind_max_time' => $summary['wind_max_time'] ?? null,
                'wind_max_gust' => $summary['wind_max_gust'] ?? null,
                'wind_max_gust_direction' => $summary['wind_max_gust_direction'] ?? null,
                'wind_max_gust_time' => $summary['wind_max_gust_time'] ?? null,
                'wind_avg_speed' => $summary['wind_avg_speed'] ?? null,
                'humidity_avg' => $summary['humidity_avg'] ?? null,
                'humidity_max' => $summary['humidity_max'] ?? null,
                'humidity_max_time' => $summary['humidity_max_time'] ?? null,
                'humidity_min' => $summary['humidity_min'] ?? null,
                'humidity_min_time' => $summary['humidity_min_time'] ?? null,
                'pressure_sea_avg' => $summary['pressure_sea_avg'] ?? null,
                'pressure_sea_max' => $summary['pressure_sea_max'] ?? null,
                'pressure_sea_max_time' => $summary['pressure_sea_max_time'] ?? null,
                'pressure_sea_min' => $summary['pressure_sea_min'] ?? null,
                'pressure_sea_min_time' => $summary['pressure_sea_min_time'] ?? null,
                'sunshine_hours' => $summary['sunshine_hours'] ?? null,
                'solar_radiation' => $summary['solar_radiation'] ?? null,
                'uv_index_avg' => $summary['uv_index_avg'] ?? null,
                'uv_index_max' => $summary['uv_index_max'] ?? null,
                'uv_index_max_time' => $summary['uv_index_max_time'] ?? null
            ];
            
            $stmt->execute($params);
            
            $rowsAffected = $stmt->rowCount();
            logInfo("Daily summary saved successfully", [
                'station_id' => $stationId,
                'date' => $date,
                'rows_affected' => $rowsAffected
            ]);
            
        } catch (PDOException $e) {
            logError("SQL Error in daily summary save", [
                'sql' => 'daily_summary_insert',
                'error' => $e->getMessage(),
                'station_id' => $stationId,
                'date' => $date,
                'params' => array_keys($params ?? [])
            ]);
            throw new Exception("Failed to save daily summary: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        logError("Failed to save daily summary", [
            'station_id' => $stationId,
            'date' => $date,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

?>