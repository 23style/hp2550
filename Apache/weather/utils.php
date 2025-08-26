<?php
/**
 * HP2550 気象データ受信システム ユーティリティ
 * 
 * データ変換・バリデーション・処理機能
 */

require_once 'config.php';

/**
 * DataConverter クラス
 * HP2550からの生データを正規化されたSI単位に変換
 */
class DataConverter {
    
    /**
     * 華氏を摂氏に変換
     */
    public static function fahrenheitToCelsius($fahrenheit) {
        return ($fahrenheit - 32) * 5 / 9;
    }
    
    /**
     * mph を m/s に変換
     */
    public static function mphToMs($mph) {
        return $mph * 0.44704;
    }
    
    /**
     * inHg を hPa に変換
     */
    public static function inHgToHpa($inHg) {
        return $inHg * 33.8638866667;
    }
    
    /**
     * inch を mm に変換
     */
    public static function inchToMm($inch) {
        return $inch * 25.4;
    }
    
    /**
     * 日時文字列をUTC DateTimeに変換
     */
    public static function parseDateTime($dateStr) {
        if (strtolower(trim($dateStr)) === 'now') {
            return new DateTime('now', new DateTimeZone('UTC'));
        }
        
        try {
            // "YYYY-MM-DD HH:MM:SS" 形式を想定
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dateStr, new DateTimeZone('UTC'));
            
            if ($dt === false) {
                logWarning("Invalid date format: $dateStr, using current time");
                return new DateTime('now', new DateTimeZone('UTC'));
            }
            
            return $dt;
            
        } catch (Exception $e) {
            logWarning("Date parsing error: " . $e->getMessage() . ", using current time");
            return new DateTime('now', new DateTimeZone('UTC'));
        }
    }
    
    /**
     * 安全に値をfloatに変換
     */
    public static function safeFloat($value, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }
        
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        logWarning("Cannot convert to float: $value");
        return $default;
    }
    
    /**
     * 安全に値をintに変換
     */
    public static function safeInt($value, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }
        
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }
        
        logWarning("Cannot convert to int: $value");
        return $default;
    }
}

/**
 * DataValidator クラス
 * データの妥当性検証
 */
class DataValidator {
    
    /**
     * 気温の妥当性チェック
     */
    public static function validateTemperature($tempC) {
        if ($tempC === null) return true;
        return $tempC >= -40 && $tempC <= 60;
    }
    
    /**
     * 湿度の妥当性チェック
     */
    public static function validateHumidity($humidity) {
        if ($humidity === null) return true;
        return $humidity >= 0 && $humidity <= 100;
    }
    
    /**
     * 気圧の妥当性チェック
     */
    public static function validatePressure($pressureHpa) {
        if ($pressureHpa === null) return true;
        return $pressureHpa >= 870 && $pressureHpa <= 1080;
    }
    
    /**
     * 風向の妥当性チェック
     */
    public static function validateWindDirection($windDir) {
        if ($windDir === null) return true;
        return $windDir >= 0 && $windDir <= 360;
    }
    
    /**
     * 正の値かチェック
     */
    public static function validatePositive($value) {
        if ($value === null) return true;
        return $value >= 0;
    }
    
    /**
     * UV指数の妥当性チェック
     */
    public static function validateUVIndex($uv) {
        if ($uv === null) return true;
        return $uv >= 0 && $uv <= 15;
    }
}

/**
 * EcowittDataProcessor クラス
 * Ecowitt形式データの処理メインクラス
 */
class EcowittDataProcessor {
    
    /**
     * 屋内・体感系キーのリスト（除外対象）
     */
    private static $excludedKeys = [
        'tempinf', 'humidityin', 'feelslike', 'feelslice', 
        'windchill', 'heatindex', 'dewpoint'
    ];
    
    /**
     * 生データを正規化されたOutdoorReadingに変換
     */
    public function processRawData($rawData) {
        logDebug("Processing raw data", ['data_keys' => array_keys($rawData)]);
        
        // 屋内・体感系キーを除外し、小文字キー化
        $filteredData = [];
        foreach ($rawData as $key => $value) {
            $lowerKey = strtolower($key);
            if (!in_array($lowerKey, self::$excludedKeys)) {
                $filteredData[$lowerKey] = $value;
            }
        }
        
        $result = [];
        
        // 時刻処理
        $timeUtc = null;
        if (isset($filteredData['dateutc'])) {
            $dt = DataConverter::parseDateTime($filteredData['dateutc']);
            $timeUtc = $dt->format('Y-m-d H:i:s');
        } else {
            $dt = new DateTime('now', new DateTimeZone('UTC'));
            $timeUtc = $dt->format('Y-m-d H:i:s');
        }
        $result['time_utc'] = $timeUtc;
        
        // 温度変換（°F → ℃）
        if (isset($filteredData['tempf'])) {
            $tempF = DataConverter::safeFloat($filteredData['tempf']);
            if ($tempF !== null) {
                $tempC = DataConverter::fahrenheitToCelsius($tempF);
                if (DataValidator::validateTemperature($tempC)) {
                    $result['temp_c'] = round($tempC, 2);
                }
            }
        }
        
        // 湿度
        if (isset($filteredData['humidity'])) {
            $humidity = DataConverter::safeFloat($filteredData['humidity']);
            if ($humidity !== null && DataValidator::validateHumidity($humidity)) {
                $result['humidity'] = round($humidity, 2);
            }
        }
        
        // 気圧（優先順位: baromrelin > baromabsin）
        $pressure = null;
        if (isset($filteredData['baromrelin'])) {
            $pressureInHg = DataConverter::safeFloat($filteredData['baromrelin']);
            if ($pressureInHg !== null) {
                $pressure = DataConverter::inHgToHpa($pressureInHg);
            }
        } elseif (isset($filteredData['baromabsin'])) {
            $pressureInHg = DataConverter::safeFloat($filteredData['baromabsin']);
            if ($pressureInHg !== null) {
                $pressure = DataConverter::inHgToHpa($pressureInHg);
            }
        }
        
        if ($pressure !== null && DataValidator::validatePressure($pressure)) {
            $result['pressure_hpa'] = round($pressure, 2);
        }
        
        // 風向
        if (isset($filteredData['winddir'])) {
            $windDir = DataConverter::safeInt($filteredData['winddir']);
            if ($windDir !== null && DataValidator::validateWindDirection($windDir)) {
                $result['wind_dir_deg'] = $windDir;
            }
        }
        
        // 風速系（mph → m/s変換）
        $windMappings = [
            'windspeedmph' => 'wind_speed_ms',
            'windspdmph_avg10m' => 'wind_speed_avg10m_ms',
            'windgustmph' => 'wind_gust_ms',
            'maxdailygust' => 'max_daily_gust_ms'
        ];
        
        foreach ($windMappings as $rawKey => $normalizedKey) {
            if (isset($filteredData[$rawKey])) {
                $windMph = DataConverter::safeFloat($filteredData[$rawKey]);
                if ($windMph !== null) {
                    $windMs = DataConverter::mphToMs($windMph);
                    if (DataValidator::validatePositive($windMs)) {
                        $result[$normalizedKey] = round($windMs, 2);
                    }
                }
            }
        }
        
        // 雨量系（inch → mm変換）
        // 注意: 週間・月間・年間降水量はデータベースに保存しない
        $rainMappings = [
            'rainratein' => 'rain_rate_mmh',
            'eventrainin' => 'rain_event_mm',
            'hourlyrainin' => 'rain_hour_mm',
            'dailyrainin' => 'rain_day_mm'
            // 'weeklyrainin' => 'rain_week_mm',   // データベースに保存しない
            // 'monthlyrainin' => 'rain_month_mm', // データベースに保存しない
            // 'yearlyrainin' => 'rain_year_mm'    // データベースに保存しない
        ];
        
        foreach ($rainMappings as $rawKey => $normalizedKey) {
            if (isset($filteredData[$rawKey])) {
                $rainInch = DataConverter::safeFloat($filteredData[$rawKey]);
                if ($rainInch !== null) {
                    $rainMm = DataConverter::inchToMm($rainInch);
                    if (DataValidator::validatePositive($rainMm)) {
                        $result[$normalizedKey] = round($rainMm, 2);
                    }
                }
            }
        }
        
        // 日射・UV
        if (isset($filteredData['solarradiation'])) {
            $solar = DataConverter::safeFloat($filteredData['solarradiation']);
            if ($solar !== null && DataValidator::validatePositive($solar)) {
                $result['solar_wm2'] = round($solar, 2);
            }
        }
        
        if (isset($filteredData['uv'])) {
            $uv = DataConverter::safeFloat($filteredData['uv']);
            if ($uv !== null && DataValidator::validateUVIndex($uv)) {
                $result['uv_index'] = round($uv, 2);
            }
        }
        
        // メタデータ
        if (isset($filteredData['stationtype'])) {
            $result['stationtype'] = substr($filteredData['stationtype'], 0, 64);
        }
        if (isset($filteredData['model'])) {
            $result['model'] = substr($filteredData['model'], 0, 64);
        }
        
        logInfo("Data processing completed", [
            'processed_fields' => count($result),
            'time_utc' => $result['time_utc']
        ]);
        
        return $result;
    }
}

/**
 * HTTPリクエスト関連のユーティリティ
 */
class RequestUtils {
    
    /**
     * POSTデータの取得とサニタイズ
     */
    public static function getPostData() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }
        
        // Content-Length チェック
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ($contentLength > MAX_REQUEST_SIZE) {
            throw new Exception('Request too large');
        }
        
        return $_POST;
    }
    
    /**
     * クライアントIPアドレス取得
     */
    public static function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_REAL_IP',            // Nginx proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // 複数IPの場合は最初のIPを取得
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * User-Agent取得
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
}

/**
 * レスポンス生成ユーティリティ
 */
class ResponseUtils {
    
    /**
     * 成功レスポンス
     */
    public static function success($data = null, $message = null) {
        $response = ['status' => 'ok'];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $response['timestamp'] = date('c');
        
        return $response;
    }
    
    /**
     * エラーレスポンス
     */
    public static function error($message, $code = null) {
        $response = [
            'status' => 'error',
            'error' => $message,
            'timestamp' => date('c')
        ];
        
        if ($code !== null) {
            $response['code'] = $code;
        }
        
        return $response;
    }
}
?>