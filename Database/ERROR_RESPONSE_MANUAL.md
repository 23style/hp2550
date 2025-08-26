# HP2550 システム エラー対応マニュアル

**作成日**: 2025-08-26  
**対象システム**: HP2550 気象データ管理システム Phase 1-3 修正版  

---

## 🚨 緊急度分類

| レベル | 影響度 | 対応時間 | 例 |
|--------|--------|----------|-----|
| **Critical** | システム全停止 | 即座 | データベース接続不可 |
| **High** | 主要機能停止 | 1時間以内 | 日別集計処理エラー |
| **Medium** | 一部機能異常 | 4時間以内 | 特定データ欠損 |
| **Low** | 軽微な警告 | 24時間以内 | ログ警告 |

---

## 1. データベース関連エラー

### 1.1 Database connection failed
```
[ERROR] Database connection failed: SQLSTATE[HY000] [2002] No such file or directory
```

**緊急度**: Critical 🔴  
**原因**: MySQL停止、接続設定不正

**対処手順**:
```bash
# 1. MySQL稼働状況確認
systemctl status mysql
# または
service mysql status

# 2. MySQL起動
systemctl start mysql

# 3. 設定確認
grep "DB_" /path/to/config.php
mysql -u root -p -e "SELECT 1;"

# 4. 権限確認
mysql -u root -p -e "SHOW GRANTS FOR 'weatheruser'@'localhost';"
```

### 1.2 SQLSTATE[23000]: Integrity constraint violation
```
[ERROR] Cannot add or update a child row: a foreign key constraint fails
```

**緊急度**: High 🟡  
**原因**: 外部キー制約違反、不正なstation_id

**対処手順**:
```sql
-- 1. 制約確認
SELECT 
    CONSTRAINT_NAME, 
    REFERENCED_TABLE_NAME, 
    REFERENCED_COLUMN_NAME 
FROM information_schema.key_column_usage 
WHERE table_name = 'daily_weather_summary';

-- 2. 有効なstation_id確認
SELECT station_id FROM weather_station;

-- 3. 問題レコード特定
SELECT DISTINCT station_id 
FROM daily_weather_summary 
WHERE station_id NOT IN (SELECT station_id FROM weather_station);
```

### 1.3 SQLSTATE[HY093]: Invalid parameter number（修正済み）
```
[ERROR] Failed to save daily summary: SQLSTATE[HY093]: Invalid parameter number
```

**緊急度**: Medium 🟡  
**原因**: SQL文のプレースホルダーとパラメータ数不一致

**対処手順**:
```bash
# Phase 1-3修正で解決済み
# 再発した場合はバージョン確認
grep "Phase 3" /path/to/daily_aggregation_functions.php

# 最新版に更新
cp backup_daily_aggregation_functions_phase2.php daily_aggregation_functions.php
```

---

## 2. 日別集計処理エラー

### 2.1 No valid data found for date
```
[ERROR] No valid data found for 2025-08-26
```

**緊急度**: Medium 🟡  
**原因**: 指定日のデータ不足

**対処手順**:
```sql
-- 1. データ存在確認
SELECT 
    COUNT(*) as total_records,
    MIN(time_utc) as first_record,
    MAX(time_utc) as last_record
FROM weather_observation 
WHERE DATE(time_utc) = '2025-08-26' AND station_id = 'AMF_hp2550';

-- 2. NULL値確認
SELECT 
    COUNT(*) as total,
    COUNT(temp_c) as temp_records,
    COUNT(humidity) as humidity_records
FROM weather_observation 
WHERE DATE(time_utc) = '2025-08-26';

-- 3. データ補完または集計スキップ判断
```

### 2.2 新機能データエラー（日照時間・UV指数）
```
[WARNING] sunshine_hours calculation resulted in negative value
```

**緊急度**: Low 🟢  
**原因**: solar_wm2データ異常

**対処手順**:
```sql
-- 1. 異常値確認
SELECT 
    time_utc,
    solar_wm2,
    uv_index
FROM weather_observation 
WHERE DATE(time_utc) = '2025-08-26' 
    AND (solar_wm2 < 0 OR solar_wm2 > 1500 OR uv_index < 0 OR uv_index > 20);

-- 2. 手動再計算
UPDATE daily_weather_summary 
SET sunshine_hours = (
    SELECT ROUND(SUM(CASE WHEN solar_wm2 >= 150 THEN 0.05 ELSE 0 END), 1)
    FROM weather_observation 
    WHERE station_id = 'AMF_hp2550' AND DATE(time_utc) = '2025-08-26'
)
WHERE station_id = 'AMF_hp2550' AND observation_date = '2025-08-26';
```

---

## 3. ファイル・システムエラー

### 3.1 Permission denied
```
[ERROR] Permission denied: Cannot write to log file
```

**緊急度**: Medium 🟡  
**原因**: ファイル権限不足

**対処手順**:
```bash
# 1. 権限確認
ls -la /path/to/weather_receiver.log

# 2. 権限修正
chmod 666 /path/to/weather_receiver.log
chown www-data:www-data /path/to/weather_receiver.log

# 3. ディレクトリ権限確認
chmod 755 /path/to/logs/
```

### 3.2 Disk space full
```
[ERROR] Cannot write to disk: No space left on device
```

**緊急度**: Critical 🔴  
**原因**: ディスク容量不足

**対処手順**:
```bash
# 1. 容量確認
df -h

# 2. 古いログファイル削除
find /path/to/logs -name "*.log.*" -mtime +30 -delete

# 3. データベースログ確認
du -sh /var/lib/mysql/

# 4. 緊急時バックアップ削除
find /path/to/backups -name "*.sql" -mtime +7 -delete
```

---

## 4. パフォーマンス関連

### 4.1 Processing timeout
```
[WARNING] Daily aggregation took 30 seconds (limit: 10s)
```

**緊急度**: Medium 🟡  
**原因**: データ量増加、インデックス不足

**対処手順**:
```sql
-- 1. インデックス確認
SHOW INDEX FROM weather_observation;

-- 2. 実行計画確認
EXPLAIN SELECT * FROM weather_observation 
WHERE station_id = 'AMF_hp2550' AND DATE(time_utc) = '2025-08-26';

-- 3. テーブル最適化
OPTIMIZE TABLE weather_observation;
ANALYZE TABLE weather_observation;

-- 4. インデックス再構築（必要時）
DROP INDEX idx_station_date ON weather_observation;
CREATE INDEX idx_station_date ON weather_observation (station_id, time_utc);
```

### 4.2 Memory limit exceeded
```
[ERROR] Fatal error: Allowed memory size exhausted
```

**緊急度**: High 🟡  
**原因**: メモリ不足、大量データ処理

**対処手順**:
```php
// 1. メモリ制限確認
ini_get('memory_limit')

// 2. 一時的制限解除（緊急時のみ）
ini_set('memory_limit', '256M');

// 3. バッチ処理化検討（根本対策）
// 日別処理を複数回に分割
```

---

## 5. 復旧手順チェックリスト

### 5.1 緊急復旧（Critical/High）
- [ ] エラー内容の詳細記録
- [ ] システム管理者への連絡
- [ ] サービス停止判断
- [ ] バックアップからの復旧実施
- [ ] 動作確認・テスト実行
- [ ] サービス再開
- [ ] 事後レポート作成

### 5.2 計画復旧（Medium/Low）
- [ ] エラー原因分析
- [ ] 修正方法検討
- [ ] テスト環境での検証
- [ ] 計画的メンテナンス実施
- [ ] 本番環境適用
- [ ] 監視強化

---

## 6. ログ分析とトラブルシューティング

### 6.1 エラーログの読み方
```
[2025-08-26 12:00:01] ERROR [127.0.0.1] SQL Error in basic statistics query {
    "sql":"basic_stats_query",
    "error":"SQLSTATE[42S22]: Column not found",
    "station_id":"AMF_hp2550",
    "date":"2025-08-26"
}
```

**分析ポイント**:
- **タイムスタンプ**: エラー発生時刻
- **sql**: 問題のSQL文識別子
- **error**: 具体的エラー内容
- **コンテキスト**: station_id, dateなど関連情報

### 6.2 よく使用する調査コマンド
```bash
# 最近のエラーログ
tail -f /path/to/weather_receiver.log | grep ERROR

# 特定期間のエラー集計
grep "2025-08-26" weather_receiver.log | grep ERROR | wc -l

# SQL特定エラー検索
grep "SQL Error" weather_receiver.log | grep "basic_stats_query"

# パフォーマンス情報
grep "execution_time\|memory_used" weather_receiver.log
```

---

## 7. 予防保守

### 7.1 定期チェック項目
```bash
# 毎日
systemctl status weather-receiver
tail -20 /path/to/weather_receiver.log

# 毎週  
php test_phase4_real_data.php --date=$(date -d "yesterday" +%Y-%m-%d)

# 毎月
mysql -u root -p weather_db -e "OPTIMIZE TABLE weather_observation, daily_weather_summary;"
```

### 7.2 アラート設定例
```bash
# エラー率監視
if [ $(grep ERROR weather_receiver.log | wc -l) -gt 10 ]; then
    echo "High error rate detected" | mail -s "HP2550 Alert" admin@example.com
fi

# ディスク容量監視
df -h | awk '$5 > 80 {print "Disk usage warning: " $0}' | mail -s "Disk Alert" admin@example.com
```

---

## 8. よくある質問（FAQ）

### Q1: 日照時間が0.0時間になる理由は？
**A**: 冬季や曇天時は正常。solar_wm2が150W/m²未満の場合は日照時間としてカウントされません。

### Q2: UV指数平均が整数でない理由は？
**A**: 仕様通り。1日の平均値のため小数点以下も表示されます。

### Q3: エラー解消後も同じエラーが発生する理由は？
**A**: キャッシュクリア、サービス再起動、設定ファイル反映確認が必要な場合があります。

---

**このマニュアルは実際の運用経験に基づいて継続的に更新してください。**