<?php
/**
 * HP2550 気象データ受信システム 設定ファイル
 * 
 * このファイルでデータベース接続情報やログ設定を管理します。
 * 本番環境では適切な値に変更してください。
 */

// エラー表示設定（本番環境では false に設定）
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// データベース設定
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '5uKtZi8NC%A&');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'weather_db');
define('DB_CHARSET', 'utf8mb4');

// ログ設定
define('LOG_ENABLED', true);
define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/logs/weather_receiver.log');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// セキュリティ設定
define('ALLOW_ORIGIN', $_ENV['ALLOW_ORIGIN'] ?? '*');
define('MAX_REQUEST_SIZE', 1024 * 1024); // 1MB

// タイムゾーン設定
date_default_timezone_set('UTC');

// データベース接続関数
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            logError("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    
    return $pdo;
}

// ログ出力関数
function writeLog($level, $message, $context = []) {
    if (!LOG_ENABLED) {
        return;
    }
    
    $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    $currentLevel = $levels[LOG_LEVEL] ?? 1;
    $messageLevel = $levels[$level] ?? 1;
    
    if ($messageLevel < $currentLevel) {
        return;
    }
    
    // ログディレクトリ作成
    $logDir = dirname(LOG_FILE);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // ログローテーション
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_SIZE) {
        rename(LOG_FILE, LOG_FILE . '.' . date('Y-m-d_H-i-s'));
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    
    $logMessage = sprintf(
        "[%s] %s [%s] %s%s\n",
        $timestamp,
        $level,
        $clientIP,
        $message,
        $contextStr
    );
    
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
}

// ログ関数のショートカット
function logDebug($message, $context = []) {
    writeLog('DEBUG', $message, $context);
}

function logInfo($message, $context = []) {
    writeLog('INFO', $message, $context);
}

function logWarning($message, $context = []) {
    writeLog('WARNING', $message, $context);
}

function logError($message, $context = []) {
    writeLog('ERROR', $message, $context);
}

// CORS設定
function setCORSHeaders() {
    header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// JSON レスポンス送信
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// エラーレスポンス送信
function sendError($message, $statusCode = 400) {
    logError($message);
    sendJSON(['error' => $message], $statusCode);
}
?>