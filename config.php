<?php
/**
 * HP2550 気象データ管理システム プロジェクト管理用設定ファイル
 * 
 * このファイルはDatabase/、migration/、tools/等のプロジェクト管理ツール用です。
 * Apache/weather/config.php とは独立して管理されます。
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

// ログ設定（プロジェクト管理用）
define('LOG_ENABLED', true);
define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/Apache/weather/logs/weather_receiver.log');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

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
            echo "Database connection failed: " . $e->getMessage() . "\n";
            exit(1);
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
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    
    $logMessage = sprintf(
        "[%s] %s %s%s\n",
        $timestamp,
        $level,
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

// コマンドライン出力関数
function echoWithTimestamp($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}
?>