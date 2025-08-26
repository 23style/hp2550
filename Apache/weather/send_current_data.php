<?php
/**
 * 現在時刻でHP2550データを送信
 */

// 現在時刻に更新したHP2550データ
$hp2550_data = [
// システム定数定義（config.php保護用）
define('HP2550_SYSTEM', true);

    'PASSKEY' => '3A139583E40073DD3D53B3EF8F1E7596',
    'stationtype' => 'EasyWeatherV1.4.0',
    'dateutc' => date('Y-m-d H:i:s'), // 現在時刻
    'tempinf' => '81.1',
    'humidityin' => '55',
    'baromrelin' => '29.079',
    'baromabsin' => '29.079',
    'tempf' => '76.1',
    'humidity' => '91',
    'winddir' => '120',
    'winddir_avg10m' => '120',
    'windspeedmph' => '0.0',
    'windspdmph_avg10m' => '0.0',
    'windgustmph' => '0.0',
    'maxdailygust' => '5.8',
    'rainratein' => '0.000',
    'eventrainin' => '0.000',
    'hourlyrainin' => '0.000',
    'dailyrainin' => '0.000',
    'weeklyrainin' => '0.028',
    'monthlyrainin' => '2.618',
    'yearlyrainin' => '7.681',
    'solarradiation' => '0.00',
    'uv' => '0',
    'wh65batt' => '0',
    'wh25batt' => '0',
    'freq' => '433M',
    'model' => 'HP2550_Pro_V1.5.8'
];

$server_url = 'http://192.168.68.161/weather/data/report/index.php';

echo "HP2550データ送信 (現在時刻版)\n";
echo "送信時刻: " . $hp2550_data['dateutc'] . "\n";
echo str_repeat('-', 50) . "\n";

// URLエンコードされたPOSTデータを作成
$postData = http_build_query($hp2550_data);

// HTTP リクエスト設定
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: HP2550_Test_Client/1.0'
        ],
        'content' => $postData
    ]
]);

// リクエスト送信
echo "送信先: $server_url\n";
echo "送信中...\n";

$startTime = microtime(true);
$response = @file_get_contents($server_url, false, $context);
$endTime = microtime(true);

$responseTime = round(($endTime - $startTime) * 1000, 2);

if ($response !== false) {
    echo "✅ 送信成功!\n";
    echo "レスポンス時間: {$responseTime}ms\n";
    echo "レスポンス内容: $response\n";
    
    // JSON整形表示
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse) {
        echo "\nJSON解析結果:\n";
        echo json_encode($jsonResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "❌ 送信失敗\n";
    $error = error_get_last();
    if ($error) {
        echo "エラー: " . $error['message'] . "\n";
    }
}
?>