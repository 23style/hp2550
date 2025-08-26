# HP2550 気象データ移行ツール

## 概要
このフォルダは、AccessからエクスポートされたCSV履歴データをweather_observationテーブルに移行するための専用ツールです。

## 重要な設計思想
- **一時的な移行専用**ツールです（本番システムには含まれません）
- **models.php**はリアルタイム観測データ専用として設計されています  
- CSV移行は**インフラ作業**であり、アプリケーションロジックではありません

## ファイル構成
```
migration/
├── README.md              # このファイル
├── csv_importer.php       # CSV専用インポータークラス
├── import_csv.php         # メイン移行スクリプト
├── debug_csv.php          # CSVデバッグツール
└── verify_import.php      # 移行結果確認ツール
```

## 使用方法

### 1. 基本的なCSV移行
```bash
cd migration/
php import_csv.php rawDataTable.csv AMF_hp2550
```

### 2. バッチサイズを指定
```bash
php import_csv.php rawDataTable.csv AMF_hp2550 500
```

### 3. CSV内容のデバッグ
```bash
php debug_csv.php rawDataTable.csv 5
```

### 4. 移行結果の確認
```bash
php verify_import.php AMF_hp2550
```

## CSVデータ要件

### 必須列
- `Time`: 観測日時（Y/n/j G:i:s または Y/m/d H:i:s 形式）
- `Outdoor Temperature(℁E`: 外気温（必須、-50°C〜80°C）

### 対応列
- `Outdoor Humidity(%)`: 外気湿度
- `REL Pressure(hpa)`: 海面補正気圧  
- `Wind Direction(°)`: 風向
- `Wind(m/s)`: 風速
- `Gust(m/s)`: 瞬間風速
- `Event Rain(mm)`: イベント降水量
- `Hourly Rain(mm)`: 時間降水量
- `Daily Rain(mm)`: 日降水量
- `Solar Rad(w/m2)`: 日射量
- `UVI`: UV指数

### 除外データ
- 外気温が-50°C未満または80°C超過
- 日時が不正な形式
- 室内データ（Indoor Temperature等）

## 機能特徴

### CSVImporter クラスの特徴
- **バッチ処理最適化**: 1000件ずつ処理（調整可能）
- **エラートレラント**: 不正データは自動スキップ
- **進捗表示**: リアルタイム処理状況表示
- **重複処理**: ON DUPLICATE KEY UPDATEで安全な再実行
- **統計レポート**: 処理/成功/スキップ件数の詳細表示

### データ品質管理
- 温度範囲チェック（-50°C〜80°C）
- 複数日時フォーマット対応
- NULL値の適切な処理
- 数値変換エラーハンドリング

## パフォーマンス仕様
- **推定処理速度**: 2,000-5,000件/秒
- **52万件の処理時間**: 約2-5分
- **メモリ使用量**: 最大50MB
- **バッチサイズ**: デフォルト1000件（調整可能）

## トラブルシューティング

### よくあるエラー
```bash
# ファイルが見つからない
❌ Error: CSV file not found: filename.csv

# データベース接続エラー  
❌ Import failed: SQLSTATE[HY000] [2002] Connection refused

# 不正なCSV形式
❌ Required CSV header missing: Time
```

### 解決方法
1. **ファイルパス確認**: CSVファイルがmigrationフォルダにあるか確認
2. **データベース接続**: `../config.php`の設定確認
3. **CSVフォーマット**: ヘッダー行が正しいか確認

## 移行後の確認

### データベース確認SQL
```sql
-- 移行件数確認
SELECT COUNT(*) FROM weather_observation WHERE station_id = 'AMF_hp2550';

-- 期間確認  
SELECT MIN(time_utc), MAX(time_utc) FROM weather_observation WHERE station_id = 'AMF_hp2550';

-- データ品質確認
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN temp_c IS NULL THEN 1 ELSE 0 END) as missing_temp,
    MIN(temp_c) as min_temp, 
    MAX(temp_c) as max_temp
FROM weather_observation WHERE station_id = 'AMF_hp2550';
```

## 注意事項
- **本番環境での実行前には必ずバックアップを取得**
- 移行は冪等性があります（同じデータの再実行は安全）
- 大量データ移行時はデータベースサーバーの負荷に注意
- 移行完了後、不要なCSVファイルは削除推奨

## システム統合後の取り扱い
移行完了後は以下の対応を推奨します：
1. **migrationフォルダの削除**または本番環境からの除外
2. **models.php**がリアルタイムデータ専用として機能することの確認
3. 日別集計システムの実行（`daily_aggregation.php`）

---
**重要**: このツールは移行専用です。日常運用では使用しないでください。