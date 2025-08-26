# HP2550 気象データ受信システム（PHP 版）

## 概要

HP2550 個人用気象観測装置から Ecowitt 形式で送信される気象データを受信し、MySQL データベースに保存する PHP アプリケーションです。お名前.com レンタルサーバーなどの標準的な PHP/Apache 環境で簡単に動作します。

## 特徴

- **多装置対応**: 複数の HP2550 装置からのデータを同時受信可能
- **データ重複対応**: 同じ時刻のデータが再送された場合は上書き保存
- **単位正規化**: Ecowitt 形式の各種単位（°F, mph, inHg, inch）を SI 単位系（℃, m/s, hPa, mm）に自動変換
- **セキュリティ**: PASSKEY による装置認証（SHA-256 ハッシュ化）
- **ログ機能**: アクセスログ、エラーログの詳細記録
- **API 提供**: RESTful API による最新データ取得
- **簡単設置**: FTP アップロードのみで即座に動作開始

## システム構成

```
HP2550 気象観測装置
    ↓ (HTTP POST, Ecowitt形式)
PHP Webアプリケーション (Apache)
    ├─ データ変換・バリデーション
    ├─ PASSKEY認証
    └─ MySQL データベース保存
```

## ファイル構成

```
hp2550/
├── config.php                      # 設定管理
├── models.php                      # データベースモデル
├── utils.php                       # データ変換・バリデーション
├── health.php                      # ヘルスチェックAPI
├── stations.php                    # ステーション一覧API
├── latest.php                      # 最新データ取得API
├── stats.php                       # 統計情報API
├── .htaccess                       # Apache設定・セキュリティ
├── README.md                       # このファイル
├── logs/                           # ログディレクトリ
├── Database/
│   ├── create_tables.sql           # テーブル作成SQL
│   └── sample_data.sql             # サンプルデータ
├── data/
│   └── report/
│       └── index.php               # HP2550データ受信エンドポイント
├── infomation/
│   ├── hp2550_sendingdata.md       # データ送信仕様
│   └── Database_plan.md            # データベース仕様
└── instructions/
    ├── environment_setup.md        # 環境構築手順書
    └── installation_guide.md       # インストール手順書
```

## クイックスタート

### 1. ファイルアップロード

FTP クライアントで Web サーバーにアップロード：

```
public_html/weather/  # アプリケーションルートディレクトリ
```

### 2. データベース作成

phpMyAdmin 等で以下を実行：

```sql
-- Database/create_tables.sql の内容をコピー＆ペースト実行
-- Database/sample_data.sql の内容をコピー＆ペースト実行
```

### 3. 設定ファイル編集

`config.php` のデータベース接続情報を編集：

```php
define('DB_HOST', 'あなたのMySQLホスト');
define('DB_USER', 'あなたのMySQLユーザー名');
define('DB_PASS', 'あなたのMySQLパスワード');
define('DB_NAME', 'weather_db');
```

### 4. 動作確認

ブラウザで以下にアクセス：

```
http://あなたのドメイン名/weather/health.php
http://あなたのドメイン名/weather/stations.php
```

## API エンドポイント

### データ受信

- **POST** `/data/report/` - HP2550 からの気象データ受信

### 確認・デバッグ用

- **GET** `/health.php` - ヘルスチェック
- **GET** `/stations.php` - 登録ステーション一覧
- **GET** `/latest.php?station_id=xxx` - 特定ステーションの最新データ
- **GET** `/stats.php` - システム統計情報

## HP2550 設定例

```
プロトコル: HTTP
サーバー: http://あなたのドメイン名
ポート番号：80
```

## データベース構造

### weather_station (ステーションマスタ)

- station_id: ステーション識別子
- passkey_sha256: PASSKEY の SHA-256 ハッシュ
- name: 表示名
- location: 設置場所
- is_active: 有効/無効フラグ

### weather_observation (観測データ)

- station_id + time_utc: 複合ユニークキー
- temp_c: 気温（℃）
- humidity: 湿度（%）
- pressure_hpa: 気圧（hPa）
- wind\_\*: 風向・風速（度, m/s）
- rain\_\*: 各種雨量（mm）
- solar_wm2: 日射量（W/m²）
- uv_index: UV 指数

## データ変換仕様

| 元の単位   | 変換後   | 変換式                 |
| ---------- | -------- | ---------------------- |
| °F → ℃     | 摂氏温度 | `C = (F - 32) × 5/9`   |
| mph → m/s  | 風速     | `ms = mph × 0.44704`   |
| inHg → hPa | 気圧     | `hPa = inHg × 33.8639` |
| inch → mm  | 雨量     | `mm = inch × 25.4`     |

## セキュリティ

- PASSKEY は平文保存せず、SHA-256 ハッシュで照合
  tools のハッシュ作成プログラムで、PASSKEY を変換してください。
- 屋内データ（tempinf, humidityin）は除外処理
- アクセスログによる不正アクセス監視
- SQL インジェクション対策（PDO プリペアドステートメント使用）
- .htaccess によるファイル保護

## 本番環境での動作

PHP/Apache 環境では追加設定不要で即座に動作開始：

- **デーモン登録**: 不要（Apache が自動処理）
- **プロセス管理**: 不要（Apache が自動処理）
- **自動起動**: Apache 起動時に自動利用可能

詳細な手順は `instructions/` フォルダの各ドキュメントを参照してください。

- daily_weather_summary にデータを登録するためには、cron やタスクスケジューラーで daily_aggregation.php を定期的に（１日１回）作動させてください。

## トラブルシューティング

### よくある問題

1. **PASSKEY 認証エラー**

   ```php
   <?php echo hash('sha256', 'your-passkey'); ?>
   ```

2. **データベース接続エラー**

   - `config.php` の接続情報を確認
   - phpMyAdmin で接続テスト

3. **PHP エラー**
   - `logs/php_errors.log` を確認
   - ファイル権限を確認（644/755）

### ログ確認

FTP またはコントロールパネルで以下を確認：

- `logs/weather_receiver.log` - アプリケーションログ
- `logs/php_errors.log` - PHP エラーログ

## サポート

技術的な問題や質問については、以下を確認してください：

1. `instructions/environment_setup.md` - 環境構築ガイド（PHP 版）
2. `infomation/` フォルダ - システム仕様書

---

**注意**: 本システムは継続的なデータ蓄積を行います。ディスク容量とデータベース容量の定期的な監視を推奨します。
