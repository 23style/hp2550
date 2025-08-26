# お名前サーバー 環境構築手順書（PHP版）

## 概要

HP2550個人用気象観測装置からのデータを受信・保存するシステムを「お名前.com」レンタルサーバー（お名前サーバー）に構築するための手順書です。

## 前提条件

- お名前.comのレンタルサーバー契約済み
- FTPアクセス権限有り（SSHは不要）
- MySQL データベース利用可能
- PHP 7.0以上が利用可能（標準提供）
- Apache Webサーバー（標準提供）

## 1. サーバー基本情報確認

### 1.1 コントロールパネルにログイン
お名前.comのサーバーコントロールパネルにアクセス

### 1.2 PHP環境確認
- PHP: バージョン 7.0以上（標準提供）
- MySQL: バージョン 5.7以上
- Apache: 標準提供

### 1.3 FTP接続情報確認
- FTPホスト名
- FTPユーザー名
- FTPパスワード
- 公開ディレクトリパス（通常 `public_html` または `www`）

## 2. ディレクトリ構成準備

### 2.1 アプリケーションディレクトリ構成
```
public_html/
├── weather/                    # アプリケーションルート
│   ├── config.php             # 設定ファイル
│   ├── models.php             # データベースモデル
│   ├── utils.php              # ユーティリティ
│   ├── health.php             # ヘルスチェック
│   ├── stations.php           # ステーション一覧
│   ├── latest.php             # 最新データ取得
│   ├── stats.php              # 統計情報
│   ├── .htaccess              # Apache設定
│   ├── logs/                  # ログディレクトリ
│   ├── Database/              # SQLスクリプト
│   └── data/
│       └── report/
│           └── index.php      # HP2550データ受信エンドポイント
```

### 2.2 ファイルアップロード
FTPクライアントを使用してファイルをアップロード：

**アップロードファイル:**
- `config.php`
- `models.php`
- `utils.php`
- `health.php`
- `stations.php`
- `latest.php`
- `stats.php`
- `.htaccess`
- `data/report/index.php`
- `Database/create_tables.sql`
- `Database/sample_data.sql`

**FTPアップロード例（FileZilla等）:**
1. FTP接続情報を入力して接続
2. サーバー側で `public_html/weather/` ディレクトリ作成
3. ローカルファイルを対応する位置にアップロード

## 3. PHP環境セットアップ（設定のみ、インストール不要）

### 3.1 ディレクトリ権限設定
FTPクライアントで以下のディレクトリを作成し、書き込み権限を付与：
- `public_html/weather/logs/` （権限: 755 または 777）

### 3.2 PHPエラーログ確認
- PHP設定はお名前サーバー側で管理済み
- エラーログは `logs/php_errors.log` に出力

## 4. データベース設定

### 4.1 データベース作成（コントロールパネル）
1. お名前.comコントロールパネルにログイン
2. 「データベース」メニューを選択
3. 新規データベース作成
   - データベース名: `weather_db`
   - ユーザー名: 任意（記録しておく）
   - パスワード: 任意（記録しておく）

### 4.2 テーブル作成
phpMyAdmin またはMySQLクライアントを使用：

1. phpMyAdminにログイン
2. `weather_db` データベースを選択
3. 「SQL」タブを選択
4. `Database/create_tables.sql` の内容をコピー＆ペーストして実行

### 4.3 サンプルデータ投入（テスト用）
1. 同じくphuipMyAdminで「SQL」タブを選択
2. `Database/sample_data.sql` の内容をコピー＆ペーストして実行

### 4.4 テーブル作成確認
phpMyAdminで以下を確認：
- `weather_station` テーブルが作成されている
- `weather_observation` テーブルが作成されている
- サンプルステーションデータが1件登録されている

## 5. 環境変数設定

### 5.1 config.php ファイル編集
FTPでダウンロードした `config.php` を編集：

```php
// データベース設定を実際の値に変更
define('DB_HOST', 'あなたのMySQLホスト名');
define('DB_PORT', 3306);
define('DB_USER', 'あなたのMySQLユーザー名');
define('DB_PASS', 'あなたのMySQLパスワード');
define('DB_NAME', 'weather_db');
```

### 5.2 設定ファイル再アップロード
編集した `config.php` をFTPでサーバーに再アップロード

## 6. アプリケーション動作確認

### 6.1 ヘルスチェック確認
Webブラウザまたはcurlで以下にアクセス：
```
http://あなたのドメイン名/weather/health.php
```

期待される応答：
```json
{
  "status": "ok",
  "timestamp": "2025-08-23T12:00:00+00:00",
  "system": {
    "php_version": "7.4.x",
    "timezone": "UTC",
    "memory_usage": 1048576,
    "memory_peak": 1048576
  },
  "database": {
    "status": "ok",
    "database": "weather_db",
    "tables": 2,
    "timestamp": "2025-08-23T12:00:00+00:00"
  }
}
```

### 6.2 ステーション一覧確認
```
http://あなたのドメイン名/weather/stations.php
```

期待される応答：
```json
{
  "status": "ok",
  "data": {
    "stations": [
      {
        "station_id": "hp2550_main",
        "name": "会津松原農園・母屋",
        "model": "HP2550_Pro_V1.5.8",
        "location": "福島県会津若松市",
        "total_observations": 1
      }
    ],
    "total_count": 1
  }
}
```

## 7. HP2550デバイス設定

### 7.1 HP2550の設定
HP2550本体またはスマートフォンアプリで以下を設定：

**カスタムサーバー設定:**
- プロトコル: HTTP
- サーバー: `http://あなたのドメイン名/weather/`
- パス: `data/report/`
- PASSKEY: `test123`（初期設定、後で変更推奨）

**送信間隔:**
- 推奨: 60秒間隔

### 7.2 データ受信テスト
HP2550の手動送信機能を使用してテスト送信を実行

### 7.3 データ受信確認
ブラウザで以下にアクセスして最新データを確認：
```
http://あなたのドメイン名/weather/latest.php?station_id=hp2550_main
```

## 8. ログ・監視設定

### 8.1 ログファイル確認
FTPクライアントまたはファイルマネージャーで以下を確認：
- `logs/weather_receiver.log` - アプリケーションログ
- `logs/php_errors.log` - PHPエラーログ

### 8.2 ログ監視
定期的に以下をチェック：
- アクセスログでデータ受信状況確認
- エラーログで問題の早期発見

### 8.3 自動ログローテーション
お名前サーバーの標準機能により自動的にログローテーションが実行されます（通常30日保持）

## 9. セキュリティ設定

### 9.1 ファイル権限設定
FTPクライアントで以下の権限を設定：
- PHPファイル: 644
- ディレクトリ: 755
- ログディレクトリ: 755 または 777（書き込み可能にする）

### 9.2 .htaccess による保護
既にアップロードした `.htaccess` ファイルにより以下が保護されます：
- 設定ファイルへの直接アクセス禁止
- ログファイルへの直接アクセス禁止
- セキュリティヘッダーの付与

## 10. 独自PASSKEYの設定（セキュリティ向上）

### 10.1 新しいPASSKEYのハッシュ値生成
以下のPHPコードを一時的に実行してハッシュ値を生成：

```php
<?php
echo hash('sha256', 'your-new-passkey-2025');
?>
```

### 10.2 データベース更新
phpMyAdminで以下のSQLを実行：

```sql
-- 既存ステーションのPASSKEY更新
UPDATE weather_station 
SET passkey_sha256 = '新しいハッシュ値'
WHERE station_id = 'hp2550_main';

-- 新しいステーション追加の場合
INSERT INTO weather_station (
    station_id, passkey_sha256, name, location, is_active
) VALUES (
    'hp2550_station2', 
    '新しいハッシュ値',
    'ステーション表示名', 
    '設置場所', 
    1
);
```

### 10.3 HP2550設定更新
HP2550のPASSKEY設定を新しい値に変更

## 11. トラブルシューティング

### 11.1 ログ確認
FTPまたはファイルマネージャーで以下をダウンロード・確認：
- `logs/weather_receiver.log`
- `logs/php_errors.log`

### 11.2 データベース接続確認
ヘルスチェックエンドポイントで確認：
```
http://あなたのドメイン名/weather/health.php
```

### 11.3 一般的な問題と解決策

| 問題 | 原因 | 解決策 |
|-----|-----|--------|
| データベース接続エラー | config.php の設定不正 | DB接続情報を再確認・修正 |
| PASSKEYエラー | ハッシュ値不一致 | SHA-256ハッシュ値を再計算 |
| PHPエラー | 構文エラー、権限不足 | php_errors.log を確認 |
| ファイルアクセス拒否 | ファイル権限不正 | FTPで権限を 644/755 に設定 |

### 11.4 動作確認手順
1. `health.php` でシステム状態確認
2. `stations.php` でステーション一覧確認
3. HP2550からテスト送信実行
4. `latest.php` で最新データ確認

## 12. 定期メンテナンス

### 12.1 ログファイル確認
月1回程度、以下をチェック：
- アクセス状況
- エラー発生有無
- ディスク使用量

### 12.2 データベースバックアップ
phpMyAdminから定期的にバックアップ実行：
1. データベース選択
2. 「エクスポート」タブ
3. 「実行」でSQLファイルダウンロード

### 12.3 システム監視
以下のエンドポイントを定期的に確認：
- `/health.php` - システム状態
- `/stats.php` - データ蓄積状況

---

以上で、お名前サーバー上でのPHP版環境構築は完了です。Python版と比べて大幅に簡単になり、デーモン登録も不要で即座に動作開始できます。