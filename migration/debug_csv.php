<?php
/**
 * CSV移行デバッグツール
 */

require_once __DIR__ . '/csv_importer.php';

if ($argc < 2) {
    echo "Usage: php debug_csv.php <csv_file> [rows_to_debug]\n";
    echo "Example: php debug_csv.php rawDataTable.csv 5\n";
    exit(1);
}

$csvFile = $argv[1];
$debugRows = isset($argv[2]) ? intval($argv[2]) : 5;

echo "=========================================\n";
echo "CSV Import Debug Tool\n";
echo "=========================================\n";
echo "CSV File: $csvFile\n";
echo "Debug rows: $debugRows\n";
echo "\n";

if (!file_exists($csvFile)) {
    die("❌ Error: CSV file '$csvFile' not found!\n");
}

$file = fopen($csvFile, 'r');
if (!$file) {
    die("❌ Error: Cannot open CSV file!\n");
}

// ヘッダー行
$headers = fgetcsv($file);
echo "Headers:\n";
foreach ($headers as $index => $header) {
    echo "  [$index] '$header'\n";
}
echo "\n";

// テスト用インポーター作成
$importer = new CSVWeatherImporter();

// 最初の数行をデバッグ
$rowCount = 0;
while (($row = fgetcsv($file)) !== false && $rowCount < $debugRows) {
    $rowCount++;
    
    echo "=== Row $rowCount ===\n";
    
    // 生データ表示
    echo "Raw CSV data:\n";
    foreach ($row as $index => $value) {
        $header = $headers[$index] ?? "col_$index";
        echo "  [$index] $header = '$value'\n";
    }
    echo "\n";
    
    // データ変換テスト
    try {
        $csvData = array_combine($headers, $row);
        
        // 外気温チェック
        $outdoorTempRaw = $csvData['Outdoor Temperature(℁E'] ?? '';
        $outdoorTemp = floatval($outdoorTempRaw);
        echo "Temperature validation:\n";
        echo "  Raw: '$outdoorTempRaw' → Float: $outdoorTemp\n";
        echo "  Valid range (-50 to 80): " . (($outdoorTemp > -50 && $outdoorTemp < 80) ? "✅" : "❌") . "\n";
        
        // 日時変換チェック
        $timeStr = $csvData['Time'] ?? '';
        echo "DateTime validation:\n";
        echo "  Raw: '$timeStr'\n";
        
        $dateTime = null;
        $formats = ['Y/n/j G:i:s', 'Y/m/d H:i:s'];
        foreach ($formats as $format) {
            $dateTime = DateTime::createFromFormat($format, $timeStr);
            if ($dateTime !== false) {
                echo "  Parsed with '$format': " . $dateTime->format('Y-m-d H:i:s') . " ✅\n";
                break;
            }
        }
        
        if (!$dateTime) {
            echo "  Parse failed ❌\n";
        }
        
        $wouldImport = ($outdoorTemp > -50 && $outdoorTemp < 80 && $dateTime);
        echo "Would import: " . ($wouldImport ? "✅ YES" : "❌ NO") . "\n";
        
    } catch (Exception $e) {
        echo "❌ Error processing row: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

fclose($file);
echo "Debug analysis completed!\n";

?>