# HP2550 システム修正 - 本番環境デプロイメント計画書

**作成日**: 2025-08-26  
**対象バージョン**: Phase 1-3 修正版  
**対象システム**: HP2550 気象データ管理システム

---

## 1. デプロイメント概要

### 1.1 修正内容サマリー
- ✅ **SQLSTATE[HY093]エラー解消** - daily_aggregation.php の複雑SQLクエリ削除
- ✅ **新機能追加** - sunshine_hours（日照時間）、uv_index_avg（UV指数平均）実装
- ✅ **データベーススキーマ変更** - 14列削除（9時/15時/21時データ、precipitation_max_1h_time）
- ✅ **エラーハンドリング強化** - 全SQL実行ポイントにtry-catch、詳細ログ出力

### 1.2 影響範囲
- **データベース**: `daily_weather_summary`テーブル構造変更
- **アプリケーション**: `daily_aggregation_functions.php`機能拡張
- **運用**: ログ出力形式改善、エラー特定機能向上

---

## 2. デプロイメント手順

### Phase A: 事前準備（サービス停止前）
```bash
# 1. 完全バックアップ作成
mysqldump -u root -p weather_db > backup_before_hp2550_fix_$(date +%Y%m%d_%H%M%S).sql

# 2. 修正ファイルの配置
cp daily_aggregation_functions.php /path/to/production/
cp test_phase4_real_data.php /path/to/production/  # テスト用

# 3. バックアップファイル確認
ls -la backup_*.sql
```

### Phase B: データベーススキーマ更新
```bash
# 1. サービス一時停止
systemctl stop weather-receiver  # 例

# 2. スキーマ変更実行
mysql -u root -p weather_db < alter_table_remove_columns.sql

# 3. 変更確認
mysql -u root -p weather_db < test_schema_changes.sql
```

### Phase C: アプリケーション更新
```bash
# 1. 修正版アプリケーションファイル配置
# （Phase Aで配置済み）

# 2. 動作確認テスト
php test_phase4_real_data.php

# 3. ログ出力確認
tail -f /path/to/weather_receiver.log
```

### Phase D: サービス再開・検証
```bash
# 1. サービス再開
systemctl start weather-receiver

# 2. 実データでの動作確認
# 既存データで日別集計実行、エラーなく完了することを確認

# 3. 新機能データ確認
# sunshine_hours、uv_index_avgにNULL以外の値が入ることを確認
```

---

## 3. ロールバック計画

### 緊急時ロールバック手順
```bash
# 1. サービス停止
systemctl stop weather-receiver

# 2. データベース復旧
mysql -u root -p weather_db < backup_before_hp2550_fix_YYYYMMDD_HHMMSS.sql

# 3. アプリケーション復旧
cp backup_daily_aggregation_functions_original.php daily_aggregation_functions.php

# 4. サービス再開
systemctl start weather-receiver
```

### ロールバック判断基準
- ✅ 新しいエラーが多発した場合
- ✅ データ集計処理が正常完了しない場合
- ✅ パフォーマンスが著しく悪化した場合

---

## 4. 検証チェックリスト

### 4.1 機能検証
- [ ] 2020-12-15 正常日でのデータ集計が正常完了
- [ ] 2020-12-16 旧問題日でのデータ集計が正常完了
- [ ] sunshine_hours が 0.0 以上の値で計算
- [ ] uv_index_avg が適切な値で計算
- [ ] 全ての既存機能が正常動作

### 4.2 パフォーマンス検証
- [ ] 日別集計処理時間が 3秒以内（288件/日の場合）
- [ ] メモリ使用量が 100KB 以内
- [ ] データベース接続エラーなし

### 4.3 エラーハンドリング検証
- [ ] 不正なstation_idでの適切なエラー処理
- [ ] データ不足時の適切なエラー処理
- [ ] ログファイルに詳細なエラー情報出力
- [ ] SQL文特定可能なログ形式

---

## 5. 運用監視ポイント

### 5.1 ログ監視
```bash
# エラーログ監視
tail -f weather_receiver.log | grep "ERROR\|Failed"

# 新機能動作確認
grep "sunshine_hours\|uv_index_avg" weather_receiver.log
```

### 5.2 データベース監視
```sql
-- 日別集計データの健全性確認
SELECT 
    COUNT(*) as total_records,
    COUNT(sunshine_hours) as sunshine_records,
    COUNT(uv_index_avg) as uv_avg_records
FROM daily_weather_summary 
WHERE observation_date >= CURDATE() - INTERVAL 7 DAY;
```

---

## 6. 成功判定基準

### デプロイメント成功とみなす条件
1. ✅ Phase A-D 全手順が正常完了
2. ✅ 検証チェックリスト全項目をクリア
3. ✅ 24時間稼働でエラー発生なし
4. ✅ 新機能（日照時間・UV指数平均）が期待通りに動作

---

## 7. 緊急連絡先

**システム管理者**: [連絡先情報]  
**データベース管理者**: [連絡先情報]  
**開発チーム**: [連絡先情報]

---

**備考**: このデプロイメントにより、HP2550システムの安定性と機能性が大幅に向上します。