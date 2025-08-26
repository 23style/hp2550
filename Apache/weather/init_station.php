<?php
/**
 * HP2550用テストステーションの初期化スクリプト
 */

require_once 'config.php';

// システム定数定義（config.php保護用）
define('HP2550_SYSTEM', true);

try {
    $pdo = getDBConnection();
    
    // PASSKEYのハッシュ化（test123）
    $passkey = 'test123';
    $passkeyHash = hash('sha256', $passkey);
    
    echo "Creating test station with PASSKEY: test123\n";
    echo "PASSKEY hash: " . $passkeyHash . "\n";
    
    // テストステーション挿入
    $stmt = $pdo->prepare("
        INSERT INTO weather_station 
        (station_id, name, model, stationtype, location, passkey_sha256, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE 
        name = VALUES(name),
        model = VALUES(model),
        stationtype = VALUES(stationtype),
        location = VALUES(location),
        passkey_sha256 = VALUES(passkey_sha256),
        is_active = VALUES(is_active)
    ");
    
    $success = $stmt->execute([
        'HP2550_TEST',
        'Test HP2550 Station',
        'HP2550',
        'EasyWeatherV1.6.0',
        'Test Location',
        $passkeyHash
    ]);
    
    if ($success) {
        echo "Test station created successfully!\n";
        echo "Station ID: HP2550_TEST\n";
        echo "PASSKEY: test123\n";
        
        // 登録確認
        $checkStmt = $pdo->prepare("SELECT * FROM weather_station WHERE station_id = ?");
        $checkStmt->execute(['HP2550_TEST']);
        $station = $checkStmt->fetch();
        
        if ($station) {
            echo "Verification successful - Station found in database\n";
        } else {
            echo "Error: Station not found after creation\n";
        }
    } else {
        echo "Failed to create test station\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>