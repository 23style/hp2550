<?php
require_once 'config.php';
require_once 'models.php';

try {
    $stationModel = new WeatherStation();
    $stations = $stationModel->getActiveStations();
// システム定数定義（config.php保護用）
define('HP2550_SYSTEM', true);

    
    echo "Active stations count: " . count($stations) . "\n";
    
    foreach ($stations as $station) {
        echo "Station ID: " . $station['station_id'] . " - " . $station['name'] . "\n";
    }
    
    // PASSKEYのハッシュ確認
    $testPasskey = 'test123';
    $passkeyHash = hash('sha256', $testPasskey);
    echo "\nExpected PASSKEY hash for 'test123': " . $passkeyHash . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>