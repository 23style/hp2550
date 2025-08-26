<?php
/**
 * CSV移行メインスクリプト
 * 
 * 使用法: php import_csv.php <csv_file> <station_id> [batch_size]
 */

require_once __DIR__ . '/csv_importer.php';

// コマンドライン引数チェック
if ($argc < 3) {
    echo "Usage: php import_csv.php <csv_file> <station_id> [batch_size]\n";
    echo "Example: php import_csv.php rawDataTable.csv AMF_hp2550\n";
    echo "Example: php import_csv.php data.csv STATION_001 500\n";
    echo "\nAvailable CSV files in current directory:\n";
    
    $csvFiles = glob("*.csv");
    if (empty($csvFiles)) {
        echo "  No CSV files found.\n";
    } else {
        foreach ($csvFiles as $file) {
            $size = round(filesize($file) / 1024 / 1024, 1);
            echo "  $file ({$size} MB)\n";
        }
    }
    exit(1);
}

$csvFile = $argv[1];
$stationId = $argv[2];
$batchSize = isset($argv[3]) ? intval($argv[3]) : 1000;

try {
    $importer = new CSVWeatherImporter($batchSize);
    $result = $importer->importFromCSV($csvFile, $stationId);
    
    // 成功時の終了コード
    exit(0);
    
} catch (Exception $e) {
    echo "❌ Import failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>