# HP2550 システム定期メンテナンス手順書

**作成日**: 2025-08-26  
**対象システム**: HP2550 気象データ管理システム Phase 1-3 修正版  
**メンテナンス責任者**: システム管理者

---

## 📅 メンテナンススケジュール概要

| 頻度 | 実施内容 | 所要時間 | ダウンタイム |
|------|----------|----------|--------------|
| **日次** | ログ確認、バックアップ確認 | 10分 | なし |
| **週次** | データ整合性チェック、パフォーマンス確認 | 30分 | なし |
| **月次** | データベース最適化、ログローテーション | 1時間 | 5分 |
| **四半期** | 全体最適化、容量計画見直し | 2時間 | 30分 |

---

## 1. 日次メンテナンス

### 1.1 システム稼働状況確認
```bash
#!/bin/bash
# daily_health_check.sh

echo "=== HP2550 日次ヘルスチェック $(date) ==="

# 1. サービス稼働確認
echo "1. サービス状況確認"
systemctl is-active weather-receiver && echo "✅ Weather service: OK" || echo "❌ Weather service: NG"

# 2. データベース接続確認
echo "2. データベース接続確認"
mysql -u root -p5uKtZi8NC%A& weather_db -e "SELECT 1;" 2>/dev/null && echo "✅ Database: OK" || echo "❌ Database: NG"

# 3. ディスク容量確認
echo "3. ディスク使用量確認"
df -h | awk '$5 > 80 {print "⚠️  " $0} $5 <= 80 {print "✅ " $0}'

# 4. 最新エラーログ確認
echo "4. 最新エラーログ（直近24時間）"
error_count=$(find /path/to/logs -name "*.log" -mtime -1 -exec grep -c "ERROR" {} + 2>/dev/null | paste -sd+ | bc)
if [ "$error_count" -gt 0 ]; then
    echo "⚠️  エラー件数: $error_count"
    grep "ERROR" /path/to/weather_receiver.log | tail -5
else
    echo "✅ エラーなし"
fi
```

### 1.2 バックアップ確認
```bash
# 自動バックアップファイル確認
backup_file=$(ls -t backup_weather_db_*.sql 2>/dev/null | head -1)
if [ -n "$backup_file" ]; then
    echo "✅ 最新バックアップ: $backup_file"
    echo "   作成日時: $(stat -c %y "$backup_file")"
    echo "   ファイルサイズ: $(du -h "$backup_file" | cut -f1)"
else
    echo "❌ バックアップファイルが見つかりません"
fi
```

---

## 2. 週次メンテナンス

### 2.1 データ整合性確認
```sql
-- weekly_data_check.sql

-- 1. 過去7日間のデータ完整性確認
SELECT 
    '過去7日間のデータ状況' as check_item,
    COUNT(*) as total_records,
    COUNT(DISTINCT observation_date) as unique_dates,
    MIN(observation_date) as earliest_date,
    MAX(observation_date) as latest_date
FROM daily_weather_summary 
WHERE observation_date >= CURDATE() - INTERVAL 7 DAY;

-- 2. 新機能データ確認（日照時間・UV指数）
SELECT 
    '新機能データ状況' as check_item,
    COUNT(*) as total_records,
    COUNT(sunshine_hours) as sunshine_records,
    COUNT(uv_index_avg) as uv_avg_records,
    ROUND(AVG(sunshine_hours), 2) as avg_sunshine,
    ROUND(AVG(uv_index_avg), 2) as avg_uv
FROM daily_weather_summary 
WHERE observation_date >= CURDATE() - INTERVAL 7 DAY;

-- 3. 異常値検出
SELECT 
    observation_date,
    sunshine_hours,
    uv_index_avg,
    uv_index_max,
    CASE 
        WHEN sunshine_hours < 0 OR sunshine_hours > 24 THEN 'sunshine_hours異常'
        WHEN uv_index_avg < 0 OR uv_index_avg > 20 THEN 'uv_index_avg異常'
        WHEN uv_index_max < 0 OR uv_index_max > 20 THEN 'uv_index_max異常'
        ELSE 'OK'
    END as status
FROM daily_weather_summary 
WHERE observation_date >= CURDATE() - INTERVAL 7 DAY
    AND (sunshine_hours < 0 OR sunshine_hours > 24 
         OR uv_index_avg < 0 OR uv_index_avg > 20
         OR uv_index_max < 0 OR uv_index_max > 20);
```

### 2.2 パフォーマンス確認
```bash
# 処理時間分析
echo "=== 週次パフォーマンス分析 ==="

# 1. 平均処理時間確認
avg_time=$(grep "execution_time" /path/to/weather_receiver.log | tail -100 | awk -F: '{sum+=$NF; count++} END {print sum/count}')
echo "平均処理時間: ${avg_time}ms"

# 2. メモリ使用量確認
avg_memory=$(grep "memory_used" /path/to/weather_receiver.log | tail -100 | awk -F: '{sum+=$NF; count++} END {print sum/count}')
echo "平均メモリ使用量: ${avg_memory}KB"

# 3. 実データテスト
echo "3. 実データテスト実行"
php test_phase4_real_data.php --date=$(date -d "yesterday" +%Y-%m-%d) --summary
```

---

## 3. 月次メンテナンス

### 3.1 データベース最適化
```sql
-- monthly_optimization.sql

-- 実行前準備
SELECT 
    TABLE_NAME,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size_MB',
    TABLE_ROWS
FROM information_schema.TABLES 
WHERE table_schema = 'weather_db'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- テーブル最適化実行
OPTIMIZE TABLE weather_station;
OPTIMIZE TABLE weather_observation;
OPTIMIZE TABLE daily_weather_summary;

-- インデックス統計更新
ANALYZE TABLE weather_station;
ANALYZE TABLE weather_observation;
ANALYZE TABLE daily_weather_summary;

-- 実行後確認
SELECT 
    TABLE_NAME,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size_MB_After',
    TABLE_ROWS
FROM information_schema.TABLES 
WHERE table_schema = 'weather_db'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
```

### 3.2 ログローテーション・クリーンアップ
```bash
#!/bin/bash
# monthly_cleanup.sh

echo "=== 月次クリーンアップ開始 $(date) ==="

# 1. 古いログファイル削除（30日以上前）
echo "1. 古いログファイル削除"
find /path/to/logs -name "*.log.*" -mtime +30 -type f -print -delete

# 2. 古いバックアップファイル削除（90日以上前）
echo "2. 古いバックアップファイル削除"
find /path/to/backups -name "backup_weather_db_*.sql" -mtime +90 -type f -print -delete

# 3. 一時ファイル削除
echo "3. 一時ファイル削除"
find /tmp -name "*weather*" -mtime +7 -type f -delete

# 4. ディスク使用量レポート
echo "4. ディスク使用量レポート"
du -sh /path/to/weather/* | sort -hr

echo "=== 月次クリーンアップ完了 ==="
```

### 3.3 セキュリティ確認
```bash
# セキュリティチェック
echo "=== セキュリティ確認 ==="

# 1. ファイル権限確認
echo "1. 重要ファイルの権限確認"
ls -la /path/to/config.php
ls -la /path/to/daily_aggregation_functions.php

# 2. データベース権限確認
mysql -u root -p -e "SELECT User, Host FROM mysql.user WHERE User LIKE '%weather%';"

# 3. ログイン試行確認
echo "3. 不正アクセス試行確認"
grep "authentication failure\|invalid user" /var/log/auth.log | tail -10
```

---

## 4. 四半期メンテナンス

### 4.1 全体最適化・チューニング
```bash
#!/bin/bash
# quarterly_tuning.sh

echo "=== 四半期最適化開始 $(date) ==="

# 1. MySQL設定最適化確認
echo "1. MySQL設定確認"
mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
mysql -u root -p -e "SHOW VARIABLES LIKE 'max_connections';"

# 2. インデックス使用状況分析
echo "2. インデックス分析"
mysql -u root -p weather_db < quarterly_index_analysis.sql

# 3. 長期パフォーマンストレンド分析
echo "3. パフォーマンストレンド分析"
grep "execution_time" /path/to/weather_receiver.log* | \
    awk '{print $1 " " $NF}' | \
    sort | \
    awk '{
        date=substr($1,2,10)
        sum[date]+=$2; count[date]++
    } END {
        for(d in sum) print d, sum[d]/count[d]
    }' > performance_trend.log
```

### 4.2 容量計画・将来予測
```sql
-- capacity_planning.sql

-- 1. データ増加トレンド分析
SELECT 
    YEAR(observation_date) as year,
    MONTH(observation_date) as month,
    COUNT(*) as records_count,
    ROUND(AVG(COUNT(*)) OVER (ORDER BY YEAR(observation_date), MONTH(observation_date) ROWS 2 PRECEDING), 0) as trend_avg
FROM daily_weather_summary 
GROUP BY YEAR(observation_date), MONTH(observation_date)
ORDER BY year, month;

-- 2. テーブルサイズ予測
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as current_size_mb,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 * 1.5, 2) as projected_size_mb_6months
FROM information_schema.TABLES 
WHERE table_schema = 'weather_db';
```

### 4.3 システム全体ヘルスレポート
```bash
# システム全体レポート生成
cat > quarterly_health_report.txt << EOF
=== HP2550 システム四半期ヘルスレポート ===
作成日時: $(date)

【システム稼働状況】
- 稼働率: $(systemctl is-active weather-receiver && echo "100%" || echo "要確認")
- 平均応答時間: $(grep "execution_time" /path/to/weather_receiver.log | tail -1000 | awk -F: '{sum+=$NF; count++} END {print sum/count "ms"}')

【データ統計】
- 総観測レコード数: $(mysql -u root -p -s -e "SELECT COUNT(*) FROM weather_db.weather_observation;" 2>/dev/null)
- 総集計レコード数: $(mysql -u root -p -s -e "SELECT COUNT(*) FROM weather_db.daily_weather_summary;" 2>/dev/null)
- 新機能データ充足率: $(mysql -u root -p -s -e "SELECT ROUND(COUNT(sunshine_hours)*100/COUNT(*), 1) FROM weather_db.daily_weather_summary;" 2>/dev/null)%

【容量状況】
$(df -h | grep -E "/$|/var|/tmp")

【エラー統計】
- 四半期エラー件数: $(find /path/to/logs -name "*.log*" -mtime -90 -exec grep -c "ERROR" {} + 2>/dev/null | paste -sd+ | bc 2>/dev/null || echo "0")

【推奨アクション】
$([ $(df / | tail -1 | awk '{print $5}' | sed 's/%//') -gt 80 ] && echo "- ディスク容量要注意" || echo "- 特になし")

EOF
```

---

## 5. 緊急時メンテナンス手順

### 5.1 システム障害時の対応
```bash
#!/bin/bash
# emergency_recovery.sh

echo "=== 緊急復旧手順開始 ==="

# 1. 現状確認
systemctl status weather-receiver
ps aux | grep php

# 2. サービス停止
systemctl stop weather-receiver

# 3. バックアップから復旧
latest_backup=$(ls -t backup_weather_db_*.sql | head -1)
mysql -u root -p weather_db < "$latest_backup"

# 4. 設定ファイル確認・復旧
cp config.php.backup config.php
cp daily_aggregation_functions.php.backup daily_aggregation_functions.php

# 5. サービス再開
systemctl start weather-receiver

# 6. 動作確認
php test_phase4_real_data.php --date=$(date +%Y-%m-%d)

echo "=== 緊急復旧手順完了 ==="
```

### 5.2 データ破損時の対応
```sql
-- data_corruption_check.sql
-- データ整合性チェック

-- 1. 外部キー制約チェック
SELECT 'Foreign Key Check' as check_type, 
       COUNT(*) as violation_count
FROM daily_weather_summary dws 
LEFT JOIN weather_station ws ON dws.station_id = ws.station_id 
WHERE ws.station_id IS NULL;

-- 2. 日付整合性チェック
SELECT 'Date Range Check' as check_type,
       COUNT(*) as invalid_count
FROM daily_weather_summary 
WHERE observation_date < '2020-01-01' OR observation_date > CURDATE() + INTERVAL 1 DAY;

-- 3. 値域チェック
SELECT 'Value Range Check' as check_type,
       COUNT(*) as invalid_count
FROM daily_weather_summary 
WHERE temp_avg < -50 OR temp_avg > 50
   OR humidity_avg < 0 OR humidity_avg > 100
   OR sunshine_hours < 0 OR sunshine_hours > 24;
```

---

## 6. メンテナンス記録テンプレート

### 6.1 日次メンテナンス記録
```
日次メンテナンス記録
実施日: ____/__/____
実施者: ____________

□ システム稼働状況確認     結果: [OK/NG] 備考: ____________
□ データベース接続確認     結果: [OK/NG] 備考: ____________
□ ディスク容量確認         結果: [OK/NG] 備考: ____________
□ エラーログ確認           結果: [OK/NG] 備考: ____________
□ バックアップ確認         結果: [OK/NG] 備考: ____________

特記事項:
_________________________________________________
```

### 6.2 月次メンテナンス記録
```
月次メンテナンス記録
実施日: ____/__/____
実施者: ____________
ダウンタイム: __:__ ～ __:__

□ データベース最適化実行   結果: [完了/要対応] 削減サイズ: ____MB
□ ログローテーション       結果: [完了/要対応] 削除ファイル数: ____
□ セキュリティ確認         結果: [OK/NG] 備考: ____________
□ パフォーマンス分析       結果: [良好/注意/要対応]

改善事項:
_________________________________________________

次回対応予定:
_________________________________________________
```

---

## 7. 自動化スクリプト設定

### 7.1 crontab設定例
```bash
# HP2550 システム定期メンテナンス設定
# crontab -e で編集

# 日次ヘルスチェック（毎日 6:00）
0 6 * * * /path/to/scripts/daily_health_check.sh >> /path/to/logs/maintenance.log 2>&1

# 週次データチェック（毎週月曜 7:00）
0 7 * * 1 mysql -u root -p weather_db < /path/to/scripts/weekly_data_check.sql >> /path/to/logs/weekly_check.log 2>&1

# 月次最適化（毎月1日 2:00）
0 2 1 * * /path/to/scripts/monthly_cleanup.sh >> /path/to/logs/monthly_maintenance.log 2>&1

# 四半期レポート（1,4,7,10月の1日 1:00）
0 1 1 1,4,7,10 * /path/to/scripts/quarterly_tuning.sh >> /path/to/logs/quarterly_maintenance.log 2>&1
```

---

## 8. メンテナンス完了基準

### 8.1 日次メンテナンス完了基準
- [ ] 全システムが正常稼働
- [ ] エラーログにCritical/Highエラーなし
- [ ] ディスク使用率80%未満
- [ ] バックアップファイル作成済み

### 8.2 月次メンテナンス完了基準
- [ ] データベース最適化完了（エラーなし）
- [ ] ログローテーション完了
- [ ] パフォーマンス基準値内
- [ ] セキュリティチェック問題なし

---

**このメンテナンス手順書を定期的に見直し、運用実績に基づいて改善していくことで、システムの長期安定稼働を実現できます。**