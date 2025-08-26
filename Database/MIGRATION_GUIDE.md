# HP2550 CSV履歴データ移行手順

## 概要
AccessからエクスポートされたCSV履歴データ（約52万件）をweather_observationテーブルに移行します。

## 前提条件
- PHPが実行できる環境（この端末にMySQLは不要）
- データベースサーバー（MySQL）にアクセス可能
- `rawdatatable.csv`が`weather/Database/`フォルダに配置済み

## CSVデータ構造
```
Time, Indoor Temperature(℃), Indoor Humidity(%), Outdoor Temperature(℃), 
Outdoor Humidity(%), Dew Point(℃), Feels Like (℃), Wind(m/s), Gust(m/s), 
Wind Direction(°), ABS Pressure(hpa), REL Pressure(hpa), Solar Rad(w/m2), 
UVI, Hourly Rain(mm), Event Rain(mm), Daily Rain(mm), Weekly Rain(mm), 
Monthly Rain(mm), Yearly Rain(mm)
```

## 移行手順

### 1. 事前準備
```bash
# Databaseフォルダに移動
cd C:\myWork\dev\hp2550\weather\Database

# CSVファイルの確認
dir rawdatatable.csv
```

### 2. データベース設定確認
`../config.php`でデータベース接続情報が正しいことを確認：
```php
define('DB_HOST', 'your_mysql_host');
define('DB_USER', 'your_mysql_user');  
define('DB_PASSWORD', 'your_mysql_password');
define('DB_NAME', 'weather_db');
```

### 3. 移行実行
```bash
# PHPで移行スクリプト実行
php migrate_csv_data.php
```

### 4. 実行結果例
```
=========================================
HP2550 CSV Data Migration Tool
=========================================
CSV File: rawdatatable.csv
Station ID: HP2550_MAIN
Batch Size: 1000

Starting data migration...
Progress: [Processed/Imported/Skipped]

[1000/980/20] - 2.3s
[2000/1960/40] - 4.7s
...
[520000/487000/33000] - 240.5s

=========================================
Migration Completed!
=========================================
Total processed: 520000
Total imported: 487000
Total skipped: 33000
Duration: 240.5 seconds
Records per second: 2024.9
=========================================
```

## 移行ロジック

### データフィルタリング
以下の条件でデータをスキップします：
- 外気温が-50℃未満または80℃超過（センサー異常）
- 日時フォーマットが不正
- 必須データが欠損

### データマッピング

| CSV列名 | データベース列名 | 変換内容 |
|---------|------------------|----------|
| Time | time_utc | Y/m/d H:i:s → Y-m-d H:i:s |
| Outdoor Temperature(℃) | temp_c | 必須フィールド |
| Outdoor Humidity(%) | humidity | - |
| REL Pressure(hpa) | pressure_hpa | 海面補正気圧を使用 |
| Wind Direction(°) | wind_dir_deg | - |
| Wind(m/s) | wind_speed_ms | - |
| Gust(m/s) | wind_gust_ms | - |
| Hourly Rain(mm) | rain_hour_mm | - |
| Event Rain(mm) | rain_event_mm | - |
| Daily Rain(mm) | rain_day_mm | - |
| Solar Rad(w/m2) | solar_wm2 | - |
| UVI | uv_index | - |

### 除外データ
- Indoor Temperature（室内温度）
- Dew Point（露点）
- Feels Like（体感温度）
- ABS Pressure（絶対気圧）
- Weekly/Monthly/Yearly Rain（週間・月間・年間降水量）

## パフォーマンス仕様
- バッチサイズ: 1000件
- 推定処理時間: 約4-8分（52万件）
- メモリ使用量: 最大50MB程度

## トラブルシューティング

### MySQL接続エラー
```
PDOException: SQLSTATE[HY000] [2002] Connection refused
```
- データベースサーバーが起動していることを確認
- `config.php`の接続情報を確認
- ネットワーク接続を確認

### メモリ不足エラー
```
PHP Fatal error: Allowed memory size exhausted
```
- `BATCH_SIZE`を500に減らす
- PHP設定で`memory_limit`を増やす

### 重複データエラー
移行スクリプトは重複データを自動的にスキップします。

## 移行後の確認

### データ件数確認
```sql
SELECT COUNT(*) as total_count FROM weather_observation;
SELECT COUNT(*) as station_count FROM weather_observation WHERE station_id = 'HP2550_MAIN';
```

### データ期間確認
```sql
SELECT 
    MIN(time_utc) as earliest_record,
    MAX(time_utc) as latest_record
FROM weather_observation 
WHERE station_id = 'HP2550_MAIN';
```

### サンプルデータ確認
```sql
SELECT * FROM weather_observation 
WHERE station_id = 'HP2550_MAIN' 
ORDER BY time_utc DESC 
LIMIT 5;
```

## 注意事項
- 移行中はシステムが重くなる可能性があります
- 移行前にデータベースのバックアップを推奨
- 移行後は不要なCSVファイルを削除してください
- 週間・月間・年間降水量は意図的に移行されません