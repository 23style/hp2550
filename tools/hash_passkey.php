<?php
/**
 * PASSKEY ハッシュ生成ツール
 * 
 * Usage: php hash_passkey.php [PASSKEY]
 * Example: php hash_passkey.php "my-secret-key-2025"
 */

// コマンドライン引数チェック
if ($argc < 2) {
    echo "PASSKEY ハッシュ生成ツール\n";
    echo "================================\n\n";
    echo "使用方法:\n";
    echo "  php hash_passkey.php [PASSKEY]\n\n";
    echo "例:\n";
    echo "  php hash_passkey.php \"test123\"\n";
    echo "  php hash_passkey.php \"my-secret-key-2025\"\n\n";
    exit(1);
}

$passkey = $argv[1];

// 入力値の確認
if (empty($passkey)) {
    echo "エラー: PASSKEYが空です\n";
    exit(1);
}

// SHA-256ハッシュ生成
$hash = hash('sha256', $passkey);

// 結果表示
echo "PASSKEY ハッシュ生成結果\n";
echo "========================\n";
echo "入力PASSKEY: " . $passkey . "\n";
echo "SHA-256ハッシュ: " . $hash . "\n\n";

// SQLサンプル表示
echo "データベース登録用SQL:\n";
echo "----------------------\n";
echo "-- 新規ステーション追加の場合\n";
echo "INSERT INTO weather_station (\n";
echo "    station_id, passkey_sha256, name, model, stationtype, location, is_active\n";
echo ") VALUES (\n";
echo "    'your_station_id',\n";
echo "    '" . $hash . "',\n";
echo "    'ステーション名',\n";
echo "    'HP2550_Pro_V1.5.8',\n";
echo "    'EasyWeatherV1.4.0',\n";
echo "    '設置場所',\n";
echo "    1\n";
echo ");\n\n";

echo "-- 既存ステーションのPASSKEY更新の場合\n";
echo "UPDATE weather_station\n";
echo "SET passkey_sha256 = '" . $hash . "'\n";
echo "WHERE station_id = 'your_station_id';\n\n";

echo "注意: SQLを実行前に station_id を適切な値に変更してください。\n";
?>