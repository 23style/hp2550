-- HP2550 気象観測データベース テーブル作成スクリプト
-- MySQL用DDL

-- データベース作成（既存の場合はスキップ）
CREATE DATABASE IF NOT EXISTS weather_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE weather_db;

-- ステーションマスタテーブル
CREATE TABLE weather_station (
    station_id VARCHAR(64) NOT NULL,
    passkey_sha256 CHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    model VARCHAR(64),
    stationtype VARCHAR(64),
    location VARCHAR(255),
    latitude DECIMAL(8,5),
    longitude DECIMAL(8,5),
    altitude_m DECIMAL(6,1),
    timezone VARCHAR(64),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (station_id),
    UNIQUE KEY uk_passkey (passkey_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 観測データテーブル
CREATE TABLE weather_observation (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    station_id VARCHAR(64) NOT NULL,
    time_utc DATETIME NOT NULL,
    temp_c DECIMAL(5,2),
    humidity DECIMAL(5,2),
    pressure_hpa DECIMAL(7,2),
    wind_dir_deg SMALLINT UNSIGNED,
    wind_speed_ms DECIMAL(5,2),
    wind_speed_avg10m_ms DECIMAL(5,2),
    wind_gust_ms DECIMAL(5,2),
    max_daily_gust_ms DECIMAL(5,2),
    rain_rate_mmh DECIMAL(6,2),
    rain_event_mm DECIMAL(7,2),
    rain_hour_mm DECIMAL(7,2),
    rain_day_mm DECIMAL(7,2),
    solar_wm2 DECIMAL(7,2),
    uv_index DECIMAL(4,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_station_time (station_id, time_utc),
    INDEX idx_time_utc (time_utc),
    INDEX idx_station_time (station_id, time_utc DESC),
    
    FOREIGN KEY (station_id) REFERENCES weather_station(station_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- データ制約チェック（MySQL 8.0以上で利用可能）
-- ALTER TABLE weather_observation 
-- ADD CONSTRAINT chk_humidity CHECK (humidity IS NULL OR (humidity >= 0 AND humidity <= 100)),
-- ADD CONSTRAINT chk_wind_dir CHECK (wind_dir_deg IS NULL OR (wind_dir_deg >= 0 AND wind_dir_deg <= 360)),
-- ADD CONSTRAINT chk_uv_index CHECK (uv_index IS NULL OR uv_index >= 0),
-- ADD CONSTRAINT chk_rain_values CHECK (
--     (rain_rate_mmh IS NULL OR rain_rate_mmh >= 0) AND
--     (rain_event_mm IS NULL OR rain_event_mm >= 0) AND
--     (rain_hour_mm IS NULL OR rain_hour_mm >= 0) AND
--     (rain_day_mm IS NULL OR rain_day_mm >= 0)
-- ),
-- ADD CONSTRAINT chk_wind_speeds CHECK (
--     (wind_speed_ms IS NULL OR wind_speed_ms >= 0) AND
--     (wind_speed_avg10m_ms IS NULL OR wind_speed_avg10m_ms >= 0) AND
--     (wind_gust_ms IS NULL OR wind_gust_ms >= 0) AND
--     (max_daily_gust_ms IS NULL OR max_daily_gust_ms >= 0)
-- );

-- 日別気象観測データ集計テーブル
CREATE TABLE daily_weather_summary (
    station_id VARCHAR(64) NOT NULL,
    observation_date DATE NOT NULL,
    
    -- 気温関連フィールド
    temp_avg DECIMAL(4,1) COMMENT '日平均気温(℃)',
    temp_max DECIMAL(4,1) COMMENT '日最高気温(℃)',
    temp_max_time TIME COMMENT '最高気温観測時刻',
    temp_min DECIMAL(4,1) COMMENT '日最低気温(℃)',
    temp_min_time TIME COMMENT '最低気温観測時刻',
    
    -- 降水量関連フィールド
    precipitation_total DECIMAL(6,1) COMMENT '日降水量(mm)',
    precipitation_max_1h DECIMAL(5,1) COMMENT '最大1時間降水量(mm)',
    
    -- 風関連フィールド
    wind_max_speed DECIMAL(4,1) COMMENT '日最大風速(m/s)',
    wind_max_direction SMALLINT COMMENT '最大風速時の風向(度)',
    wind_max_time TIME COMMENT '最大風速観測時刻',
    wind_max_gust DECIMAL(4,1) COMMENT '日最大瞬間風速(m/s)',
    wind_max_gust_direction SMALLINT COMMENT '最大瞬間風速時の風向(度)',
    wind_max_gust_time TIME COMMENT '最大瞬間風速観測時刻',
    wind_avg_speed DECIMAL(4,1) COMMENT '日平均風速(m/s)',
    
    -- 湿度・気圧関連フィールド
    humidity_avg DECIMAL(4,1) COMMENT '日平均相対湿度(%)',
    humidity_max SMALLINT COMMENT '日最大相対湿度(%)',
    humidity_max_time TIME COMMENT '最大湿度観測時刻',
    humidity_min SMALLINT COMMENT '日最小相対湿度(%)',
    humidity_min_time TIME COMMENT '最小湿度観測時刻',
    pressure_sea_avg DECIMAL(6,1) COMMENT '日平均海面更正気圧(hPa)',
    pressure_sea_max DECIMAL(6,1) COMMENT '日最高海面更正気圧(hPa)',
    pressure_sea_max_time TIME COMMENT '最高気圧観測時刻',
    pressure_sea_min DECIMAL(6,1) COMMENT '日最低海面更正気圧(hPa)',
    pressure_sea_min_time TIME COMMENT '最低気圧観測時刻',
    
    -- 日照・日射関連フィールド
    sunshine_hours DECIMAL(3,1) COMMENT '日照時間(時間)',
    solar_radiation DECIMAL(4,1) COMMENT '全天日射量(MJ/m²)',
    uv_index_avg DECIMAL(3,1) COMMENT '日平均UVインデックス',
    uv_index_max DECIMAL(3,1) COMMENT '最大UVインデックス',
    uv_index_max_time TIME COMMENT '最大UVインデックス時刻',
    
    -- システム管理フィールド
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'レコード更新日時',
    
    -- 制約定義
    PRIMARY KEY (station_id, observation_date),
    
    -- インデックス
    INDEX idx_date (observation_date),
    INDEX idx_station_date (station_id, observation_date),
    INDEX idx_temp_max (temp_max),
    INDEX idx_precip_total (precipitation_total),
    
    -- 外部キー制約
    FOREIGN KEY (station_id) REFERENCES weather_station(station_id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='日別気象観測データ集計テーブル';

-- データ制約チェック（MySQL 8.0以上）
ALTER TABLE daily_weather_summary 
ADD CONSTRAINT chk_daily_humidity_range CHECK (
    (humidity_avg IS NULL OR (humidity_avg >= 0 AND humidity_avg <= 100)) AND
    (humidity_max IS NULL OR (humidity_max >= 0 AND humidity_max <= 100)) AND
    (humidity_min IS NULL OR (humidity_min >= 0 AND humidity_min <= 100))
),
ADD CONSTRAINT chk_daily_wind_direction CHECK (
    (wind_max_direction IS NULL OR (wind_max_direction >= 0 AND wind_max_direction <= 360)) AND
    (wind_max_gust_direction IS NULL OR (wind_max_gust_direction >= 0 AND wind_max_gust_direction <= 360))
),
ADD CONSTRAINT chk_daily_precipitation CHECK (
    (precipitation_total IS NULL OR precipitation_total >= 0) AND
    (precipitation_max_1h IS NULL OR precipitation_max_1h >= 0)
),
ADD CONSTRAINT chk_daily_wind_speeds CHECK (
    (wind_max_speed IS NULL OR wind_max_speed >= 0) AND
    (wind_max_gust IS NULL OR wind_max_gust >= 0) AND
    (wind_avg_speed IS NULL OR wind_avg_speed >= 0)
),
ADD CONSTRAINT chk_daily_uv_index CHECK (
    (uv_index_avg IS NULL OR uv_index_avg >= 0) AND
    (uv_index_max IS NULL OR uv_index_max >= 0)
),
ADD CONSTRAINT chk_sunshine_hours CHECK (
    sunshine_hours IS NULL OR (sunshine_hours >= 0 AND sunshine_hours <= 24)
);
