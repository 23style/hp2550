<?php
/**
 * HP2550 気象データ受信システム データベースモデル
 */

require_once 'config.php';

/**
 * WeatherStation クラス
 * 気象ステーションの管理
 */
class WeatherStation {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * PASSKEYでステーションを検索・認証
     */
    public function findByPasskey($passkey) {
        $passkeyHash = hash('sha256', $passkey);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM weather_station 
            WHERE passkey_sha256 = ? AND is_active = 1
        ");
        
        $stmt->execute([$passkeyHash]);
        $result = $stmt->fetch();
        
        if ($result) {
            logInfo("Station authenticated", ['station_id' => $result['station_id']]);
        } else {
            logWarning("Invalid PASSKEY attempt", ['passkey_hash' => $passkeyHash]);
        }
        
        return $result;
    }
    
    /**
     * 有効なステーション一覧取得
     */
    public function getActiveStations() {
        $stmt = $this->pdo->prepare("
            SELECT station_id, name, model, stationtype, location, 
                   latitude, longitude, created_at
            FROM weather_station 
            WHERE is_active = 1
            ORDER BY created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 特定ステーションの取得
     */
    public function getById($stationId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM weather_station 
            WHERE station_id = ? AND is_active = 1
        ");
        
        $stmt->execute([$stationId]);
        return $stmt->fetch();
    }
}

/**
 * WeatherObservation クラス
 * 気象観測データの管理
 */
class WeatherObservation {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * 観測データの保存（UPSERT）
     */
    public function saveObservation($stationId, $observationData) {
        try {
            $this->pdo->beginTransaction();
            
            // 重複チェック・削除（同じ station_id + time_utc）
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM weather_observation 
                WHERE station_id = ? AND time_utc = ?
            ");
            $deleteStmt->execute([$stationId, $observationData['time_utc']]);
            
            // 新しいデータ挿入（リアルタイム観測データ用）
            $insertStmt = $this->pdo->prepare("
                INSERT INTO weather_observation (
                    station_id, time_utc, temp_c, humidity, pressure_hpa,
                    wind_dir_deg, wind_speed_ms, wind_speed_avg10m_ms,
                    wind_gust_ms, max_daily_gust_ms, rain_rate_mmh,
                    rain_event_mm, rain_hour_mm, rain_day_mm,
                    solar_wm2, uv_index
                ) VALUES (
                    :station_id, :time_utc, :temp_c, :humidity, :pressure_hpa,
                    :wind_dir_deg, :wind_speed_ms, :wind_speed_avg10m_ms,
                    :wind_gust_ms, :max_daily_gust_ms, :rain_rate_mmh,
                    :rain_event_mm, :rain_hour_mm, :rain_day_mm,
                    :solar_wm2, :uv_index
                )
            ");
            
            // model/stationtypeをデータから除外
            $insertData = array_merge(['station_id' => $stationId], $observationData);
            unset($insertData['model'], $insertData['stationtype']);
            
            $insertStmt->execute($insertData);
            
            $this->pdo->commit();
            
            logInfo("Data saved successfully", [
                'station_id' => $stationId,
                'time_utc' => $observationData['time_utc']
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollback();
            logError("Failed to save observation data", [
                'error' => $e->getMessage(),
                'station_id' => $stationId
            ]);
            throw $e;
        }
    }
    
    /**
     * 特定ステーションの最新データ取得（JOINでmodel/stationtype取得）
     */
    public function getLatestByStation($stationId) {
        $stmt = $this->pdo->prepare("
            SELECT wo.*, ws.model, ws.stationtype 
            FROM weather_observation wo
            JOIN weather_station ws ON wo.station_id = ws.station_id
            WHERE wo.station_id = ?
            ORDER BY wo.time_utc DESC, wo.created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$stationId]);
        $result = $stmt->fetch();
        
        if ($result) {
            // NULL値を除外してレスポンス用に整形
            return array_filter($result, function($value) {
                return $value !== null;
            });
        }
        
        return null;
    }
    
    /**
     * ステーション別のデータ件数取得
     */
    public function getDataCount($stationId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM weather_observation 
            WHERE station_id = ?
        ");
        
        $stmt->execute([$stationId]);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    }
    
    /**
     * 指定期間のデータ取得（JOINでmodel/stationtype取得）
     */
    public function getDataByPeriod($stationId, $startDate, $endDate, $limit = 1000) {
        $stmt = $this->pdo->prepare("
            SELECT wo.*, ws.model, ws.stationtype 
            FROM weather_observation wo
            JOIN weather_station ws ON wo.station_id = ws.station_id
            WHERE wo.station_id = ? 
            AND wo.time_utc BETWEEN ? AND ?
            ORDER BY wo.time_utc DESC
            LIMIT ?
        ");
        
        $stmt->execute([$stationId, $startDate, $endDate, $limit]);
        $results = $stmt->fetchAll();
        
        // NULL値を除外
        return array_map(function($row) {
            return array_filter($row, function($value) {
                return $value !== null;
            });
        }, $results);
    }
    
    /**
     * データベース統計情報取得
     */
    public function getStatistics() {
        $stats = [];
        
        // 総ステーション数
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count FROM weather_station WHERE is_active = 1
        ");
        $stats['total_stations'] = $stmt->fetch()['count'];
        
        // 総観測データ数
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM weather_observation");
        $stats['total_observations'] = $stmt->fetch()['count'];
        
        // 最新データ日時
        $stmt = $this->pdo->query("
            SELECT MAX(time_utc) as latest_time FROM weather_observation
        ");
        $stats['latest_observation_time'] = $stmt->fetch()['latest_time'];
        
        // ステーション別データ数
        $stmt = $this->pdo->query("
            SELECT ws.station_id, ws.name, COUNT(wo.id) as observation_count,
                   MAX(wo.time_utc) as last_observation
            FROM weather_station ws
            LEFT JOIN weather_observation wo ON ws.station_id = wo.station_id
            WHERE ws.is_active = 1
            GROUP BY ws.station_id, ws.name
            ORDER BY observation_count DESC
        ");
        $stats['station_stats'] = $stmt->fetchAll();
        
        return $stats;
    }
}

/**
 * データベースヘルスチェック
 */
function checkDatabaseHealth() {
    try {
        $pdo = getDBConnection();
        
        // 基本接続テスト
        $stmt = $pdo->query("SELECT 1");
        
        // テーブル存在チェック
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM information_schema.tables 
            WHERE table_schema = '" . DB_NAME . "' 
            AND table_name IN ('weather_station', 'weather_observation')
        ");
        $tableCount = $stmt->fetch()['count'];
        
        if ($tableCount < 2) {
            throw new Exception("Required tables not found");
        }
        
        return [
            'status' => 'ok',
            'database' => DB_NAME,
            'tables' => $tableCount,
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        logError("Database health check failed", ['error' => $e->getMessage()]);
        return [
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ];
    }
}
?>