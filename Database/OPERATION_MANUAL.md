# HP2550 システム運用マニュアル

**作成日**: 2025-08-26  
**対象システム**: HP2550 気象データ管理システム  
**対象バージョン**: Phase 1-3 修正完了版

---

## 1. 日常運用手順

### 1.1 毎日の確認事項
```bash
# 1. システム稼働状況確認
systemctl status weather-receiver
curl http://localhost/weather/health.php

# 2. ログ確認（エラーがないか）
tail -n 50 /path/to/weather_receiver.log | grep ERROR

# 3. 日別集計処理確認
php test_phase4_real_data.php --date=$(date -d "yesterday" +%Y-%m-%d)
```

### 1.2 週次確認事項
```sql
-- データベース健全性確認
SELECT 
    COUNT(*) as total_records,
    COUNT(sunshine_hours) as sunshine_records,
    COUNT(uv_index_avg) as uv_avg_records,
    MIN(observation_date) as earliest_date,
    MAX(observation_date) as latest_date
FROM daily_weather_summary 
WHERE observation_date >= CURDATE() - INTERVAL 7 DAY;
```

---

## 2. エラー対応手順

### 2.1 SQLSTATE[HY093] エラー（修正済み）
**症状**: "Invalid parameter number" エラー
**対処**: Phase 1-3修正で解決済み。このエラーが再発した場合は設定確認。

### 2.2 Database connection failed エラー
```bash
# 1. MySQL稼働確認
systemctl status mysql

# 2. 接続テスト
mysql -u root -p weather_db -e "SELECT 1;"

# 3. 設定確認
grep "DB_" /path/to/config.php
```

### 2.3 日別集計処理エラー
```bash
# 1. 手動実行でエラー内容確認
php test_phase4_real_data.php --date=2025-08-26 --verbose

# 2. ログ詳細確認
grep "daily_summary" weather_receiver.log | tail -20

# 3. データ整合性確認
SELECT COUNT(*) FROM weather_observation WHERE DATE(time_utc) = '2025-08-26';
```

### 2.4 新機能エラー（日照時間・UV指数）
```sql
-- データ確認
SELECT 
    station_id,
    observation_date,
    sunshine_hours,
    uv_index_avg,
    uv_index_max,
    solar_radiation
FROM daily_weather_summary 
WHERE observation_date = CURDATE()
    AND (sunshine_hours IS NULL OR uv_index_avg IS NULL);
```

---

## 3. ログ分析ガイド

### 3.1 正常なログパターン
```
[2025-08-26 12:00:00] INFO [127.0.0.1] Starting daily summary calculation {"station_id":"AMF_hp2550","date":"2025-08-26"}
[2025-08-26 12:00:02] INFO [127.0.0.1] Basic statistics calculated successfully {"records_found":true}
[2025-08-26 12:00:02] INFO [127.0.0.1] Daily summary calculation completed {"station_id":"AMF_hp2550","date":"2025-08-26"}
```

### 3.2 エラーログパターン
```bash
# SQL特定可能なエラーログ例
grep "SQL Error" weather_receiver.log
# [2025-08-26 12:00:01] ERROR [127.0.0.1] SQL Error in basic statistics query {"sql":"basic_stats_query","error":"..."}
```

### 3.3 パフォーマンス分析
```bash
# 処理時間の確認
grep "execution_time\|memory_used" weather_receiver.log
```

---

## 4. データ整合性チェック

### 4.1 日次データ整合性
```sql
-- 欠損日の確認
SELECT observation_date 
FROM (
    SELECT CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY as observation_date
    FROM (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) as a
    CROSS JOIN (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) as b
) dates
LEFT JOIN daily_weather_summary dws ON dates.observation_date = dws.observation_date AND dws.station_id = 'AMF_hp2550'
WHERE dates.observation_date >= CURDATE() - INTERVAL 30 DAY
    AND dws.observation_date IS NULL
ORDER BY dates.observation_date;
```

### 4.2 新機能データ確認
```sql
-- 日照時間・UV指数の値域確認
SELECT 
    observation_date,
    sunshine_hours,
    uv_index_avg,
    uv_index_max,
    CASE 
        WHEN sunshine_hours < 0 OR sunshine_hours > 24 THEN 'Invalid sunshine_hours'
        WHEN uv_index_avg < 0 OR uv_index_avg > 20 THEN 'Invalid uv_index_avg'
        WHEN uv_index_max < 0 OR uv_index_max > 20 THEN 'Invalid uv_index_max'
        ELSE 'OK'
    END as validation_status
FROM daily_weather_summary 
WHERE observation_date >= CURDATE() - INTERVAL 7 DAY
    AND validation_status != 'OK';
```

---

## 5. パフォーマンス監視

### 5.1 処理時間基準値
- **日別集計処理**: 3秒以内（288件/日の場合）
- **メモリ使用量**: 100KB以内
- **データベースクエリ**: 1秒以内/クエリ

### 5.2 アラート条件
```bash
# 処理時間が5秒を超える場合
grep "execution_time" weather_receiver.log | awk '{if($NF > 5000) print $0}'

# エラー率が5%を超える場合
error_count=$(grep "ERROR" weather_receiver.log | wc -l)
total_count=$(grep "daily summary" weather_receiver.log | wc -l)
error_rate=$(echo "scale=2; $error_count * 100 / $total_count" | bc)
```

---

## 6. バックアップ・復旧手順

### 6.1 日次バックアップ
```bash
# 自動バックアップスクリプト実行
./backup_database_simple.bat

# バックアップファイル確認
ls -la backup_weather_db_*.sql
```

### 6.2 緊急復旧手順
```bash
# 1. システム停止
systemctl stop weather-receiver

# 2. データベース復旧
mysql -u root -p weather_db < backup_weather_db_YYYYMMDD.sql

# 3. システム再開
systemctl start weather-receiver

# 4. 動作確認
php test_phase4_real_data.php --date=$(date +%Y-%m-%d)
```

---

## 7. 定期メンテナンス

### 7.1 月次メンテナンス
```bash
# 1. ログローテーション確認
find /path/to/logs -name "*.log.*" -mtime +30 -delete

# 2. データベース最適化
mysql -u root -p weather_db -e "OPTIMIZE TABLE weather_observation, daily_weather_summary;"

# 3. インデックス統計更新
mysql -u root -p weather_db -e "ANALYZE TABLE weather_observation, daily_weather_summary;"
```

### 7.2 四半期メンテナンス
- データベーススキーマ確認
- パフォーマンスチューニング検討
- 古いデータのアーカイブ検討

---

## 8. 緊急連絡先・エスカレーション

### 8.1 対応レベル
**Level 1**: ログ確認、サービス再起動で解決  
**Level 2**: データベース復旧、設定変更が必要  
**Level 3**: コード修正、スキーマ変更が必要

### 8.2 連絡先
- **システム管理者**: [連絡先]
- **データベース管理者**: [連絡先]
- **開発チーム**: [連絡先]

---

**このマニュアルを定期的に更新し、運用経験を蓄積していくことで、システムの安定稼働を維持できます。**