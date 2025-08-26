-- サンプルデータ挿入スクリプト
-- テスト用のステーションとサンプル観測データ

USE weather_db;

-- サンプルステーション（PASSKEYはSHA-256でハッシュ化して登録）
INSERT INTO weather_station (
    station_id, 
    passkey_sha256, 
    name, 
    model, 
    stationtype, 
    location, 
    latitude, 
    longitude, 
    altitude_m, 
    timezone, 
    is_active
) VALUES (
    'AMF_hp2550', 
    'c67c66ec0a2b5e7d376c99e9f9fc647db06334299c8dba392e311e29b6644959', -- SHA256
    '会津松原農園', 
    'HP2550_Pro_V1.5.8', 
    'EasyWeatherV1.4.0', 
    '福島県会津若松市', 
    37.2647, 
    139.54448, 
    238.0, 
    'Asia/Tokyo', 
    1
);
