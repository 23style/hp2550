<?php
/**
 * CSV専用データインポータークラス
 * 
 * 目的: 過去の気象データ（AccessエクスポートCSV）を一括インポート
 * 特徴: バッチ処理最適化、エラートレラント、進捗表示
 */

require_once __DIR__ . '/../config.php';

class CSVWeatherImporter {
    private $pdo;
    private $batchSize;
    private $totalProcessed = 0;
    private $totalImported = 0;
    private $totalSkipped = 0;
    
    public function __construct($batchSize = 1000) {
        $this->pdo = getDBConnection();
        $this->batchSize = $batchSize;
    }
    
    /**
     * CSVファイルをインポート
     */
    public function importFromCSV($csvFilePath, $stationId) {
        $startTime = microtime(true);
        
        echo "=========================================\n";
        echo "CSV Weather Data Import\n";
        echo "=========================================\n";
        echo "CSV File: $csvFilePath\n";
        echo "Station ID: $stationId\n";
        echo "Batch Size: {$this->batchSize}\n";
        echo "\n";
        
        // ファイル存在チェック
        if (!file_exists($csvFilePath)) {
            throw new Exception("CSV file not found: $csvFilePath");
        }
        
        // ステーション確認・作成
        $this->ensureStationExists($stationId);
        
        // CSVファイル処理
        $csvFile = fopen($csvFilePath, 'r');
        if (!$csvFile) {
            throw new Exception("Cannot open CSV file: $csvFilePath");
        }
        
        // ヘッダー読み込み
        $headers = fgetcsv($csvFile);
        $this->validateCSVHeaders($headers);
        
        echo "Starting data import...\n";
        echo "Progress: [Processed/Imported/Skipped]\n\n";
        
        $batchData = [];
        
        // データ行処理
        while (($row = fgetcsv($csvFile)) !== false) {
            $this->totalProcessed++;
            
            try {
                $observationData = $this->processCSVRow($headers, $row);
                
                if ($observationData) {
                    $batchData[] = $observationData;
                    
                    // バッチサイズに達したら保存
                    if (count($batchData) >= $this->batchSize) {
                        $this->saveBatch($stationId, $batchData);
                        $batchData = [];
                    }
                }
                
            } catch (Exception $e) {
                $this->totalSkipped++;
                // エラーログ（オプション）
                error_log("CSV row {$this->totalProcessed} error: " . $e->getMessage());
            }
            
            // 進捗表示
            if ($this->totalProcessed % 1000 == 0) {
                $elapsed = round(microtime(true) - $startTime, 1);
                echo "[{$this->totalProcessed}/{$this->totalImported}/{$this->totalSkipped}] - {$elapsed}s\n";
            }
        }
        
        // 残りのバッチ処理
        if (!empty($batchData)) {
            $this->saveBatch($stationId, $batchData);
        }
        
        fclose($csvFile);
        
        // 完了レポート
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        echo "\n=========================================\n";
        echo "Import Completed!\n";
        echo "=========================================\n";
        echo "Total processed: {$this->totalProcessed}\n";
        echo "Total imported: {$this->totalImported}\n";
        echo "Total skipped: {$this->totalSkipped}\n";
        echo "Duration: {$duration} seconds\n";
        echo "Records per second: " . round($this->totalImported / max($duration, 1), 1) . "\n";
        echo "=========================================\n";
        
        return [
            'processed' => $this->totalProcessed,
            'imported' => $this->totalImported,
            'skipped' => $this->totalSkipped,
            'duration' => $duration
        ];
    }
    
    /**
     * ステーションの存在確認・作成
     */
    private function ensureStationExists($stationId) {
        $stmt = $this->pdo->prepare("SELECT station_id FROM weather_station WHERE station_id = ?");
        $stmt->execute([$stationId]);
        
        if (!$stmt->fetch()) {
            echo "Creating new station: $stationId\n";
            
            $stmt = $this->pdo->prepare("
                INSERT INTO weather_station (
                    station_id, passkey_sha256, name, model, 
                    stationtype, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $passkeyHash = hash('sha256', 'MIGRATION_' . $stationId);
            $stmt->execute([
                $stationId,
                $passkeyHash,
                $stationId . ' Historical Data',
                'HP2550',
                'WeatherStation'
            ]);
            
            echo "✅ Station created\n\n";
        } else {
            echo "✅ Station exists\n\n";
        }
    }
    
    /**
     * CSVヘッダーの検証
     */
    private function validateCSVHeaders($headers) {
        $requiredHeaders = ['Time', 'Outdoor Temperature(℁E'];
        
        foreach ($requiredHeaders as $required) {
            if (!in_array($required, $headers)) {
                throw new Exception("Required CSV header missing: $required");
            }
        }
        
        echo "CSV headers validated ✅\n";
    }
    
    /**
     * CSV行データの処理・変換
     */
    private function processCSVRow($headers, $row) {
        $csvData = array_combine($headers, $row);
        
        // 外気温チェック（必須フィールド）
        $outdoorTemp = floatval($csvData['Outdoor Temperature(℁E'] ?? 0);
        if ($outdoorTemp <= -50 || $outdoorTemp >= 80) {
            $this->totalSkipped++;
            return null; // 異常値はスキップ
        }
        
        // 時刻変換
        $timeStr = $csvData['Time'] ?? '';
        $dateTime = $this->parseDateTime($timeStr);
        if (!$dateTime) {
            $this->totalSkipped++;
            return null; // 不正な日時はスキップ
        }
        
        // 観測データ構築（CSVに特化）
        return [
            'time_utc' => $dateTime->format('Y-m-d H:i:s'),
            'temp_c' => $outdoorTemp,
            'humidity' => $this->safeFloat($csvData['Outdoor Humidity(%)'] ?? null),
            'pressure_hpa' => $this->safeFloat($csvData['REL Pressure(hpa)'] ?? null),
            'wind_dir_deg' => $this->safeInt($csvData['Wind Direction(°)'] ?? null),
            'wind_speed_ms' => $this->safeFloat($csvData['Wind(m/s)'] ?? null),
            'wind_gust_ms' => $this->safeFloat($csvData['Gust(m/s)'] ?? null),
            'rain_rate_mmh' => null, // CSVには含まれない
            'rain_event_mm' => $this->safeFloat($csvData['Event Rain(mm)'] ?? null),
            'rain_hour_mm' => $this->safeFloat($csvData['Hourly Rain(mm)'] ?? null),
            'rain_day_mm' => $this->safeFloat($csvData['Daily Rain(mm)'] ?? null),
            'solar_wm2' => $this->safeFloat($csvData['Solar Rad(w/m2)'] ?? null),
            'uv_index' => $this->safeFloat($csvData['UVI'] ?? null),
        ];
    }
    
    /**
     * バッチデータの保存
     */
    private function saveBatch($stationId, $batchData) {
        $sql = "
            INSERT INTO weather_observation (
                station_id, time_utc, temp_c, humidity, pressure_hpa,
                wind_dir_deg, wind_speed_ms, wind_gust_ms,
                rain_rate_mmh, rain_event_mm, rain_hour_mm, rain_day_mm,
                solar_wm2, uv_index
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                temp_c = VALUES(temp_c),
                humidity = VALUES(humidity),
                pressure_hpa = VALUES(pressure_hpa),
                created_at = NOW()
        ";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($batchData as $data) {
            try {
                $stmt->execute([
                    $stationId,
                    $data['time_utc'],
                    $data['temp_c'],
                    $data['humidity'],
                    $data['pressure_hpa'],
                    $data['wind_dir_deg'],
                    $data['wind_speed_ms'],
                    $data['wind_gust_ms'],
                    $data['rain_rate_mmh'],
                    $data['rain_event_mm'],
                    $data['rain_hour_mm'],
                    $data['rain_day_mm'],
                    $data['solar_wm2'],
                    $data['uv_index']
                ]);
                
                $this->totalImported++;
                
            } catch (Exception $e) {
                // 重複エラーなどは無視（ログのみ）
                error_log("Batch save error: " . $e->getMessage());
                $this->totalSkipped++;
            }
        }
    }
    
    /**
     * 日時解析（複数フォーマット対応）
     */
    private function parseDateTime($timeStr) {
        $formats = ['Y/n/j G:i:s', 'Y/m/d H:i:s', 'n/j/Y G:i:s', 'm/d/Y H:i:s'];
        
        foreach ($formats as $format) {
            $dateTime = DateTime::createFromFormat($format, $timeStr);
            if ($dateTime !== false) {
                return $dateTime;
            }
        }
        
        return null;
    }
    
    /**
     * 安全なfloat変換
     */
    private function safeFloat($value) {
        if ($value === null || $value === '' || $value === '0') {
            return ($value === '0') ? 0.0 : null;
        }
        return is_numeric($value) ? (float) $value : null;
    }
    
    /**
     * 安全なint変換
     */
    private function safeInt($value) {
        if ($value === null || $value === '' || $value === '0') {
            return ($value === '0') ? 0 : null;
        }
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
    
    /**
     * インポート統計取得
     */
    public function getStatistics() {
        return [
            'processed' => $this->totalProcessed,
            'imported' => $this->totalImported,
            'skipped' => $this->totalSkipped
        ];
    }
}

?>