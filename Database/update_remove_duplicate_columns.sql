-- weather_observationテーブルから重複カラムを削除するためのSQLスクリプト
-- 実行前に必ずデータベースのバックアップを取ってください

USE weather_db;

-- 既存のweather_observationテーブルから model と stationtype カラムを削除
ALTER TABLE weather_observation 
DROP COLUMN IF EXISTS stationtype,
DROP COLUMN IF EXISTS model;

-- 更新確認用クエリ
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'weather_db' 
  AND TABLE_NAME = 'weather_observation'
ORDER BY ORDINAL_POSITION;