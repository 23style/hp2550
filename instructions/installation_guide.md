# HP2550 気象データ受信システム インストール手順書（PHP版）

## 概要

このドキュメントは、HP2550個人用気象観測装置からのデータを受信・蓄積するPHPアプリケーションの詳細なインストール手順を説明します。

## システム構成

```
HP2550 気象観測装置 
    ↓ (HTTP POST / Ecowitt形式)
お名前サーバー
    ├─ PHP Webアプリケーション (Apache)
    ├─ MySQL データベース  
    └─ ログファイル
```

## 必要な環境

- **Webサーバー**: Apache（お名前サーバー標準提供）
- **PHP**: 7.0以上（お名前サーバー標準提供）
- **データベース**: MySQL 5.7以上（お名前サーバー標準提供）
- **メモリ**: 特別な要求なし（標準的な共有サーバーで充分）
- **ディスク**: 最低100MB（データ蓄積により増加）
- **ネットワーク**: HTTP(80/443番ポート) - 標準Webアクセス

## 事前準備

### 1. お名前サーバー契約情報の確認

以下の情報を事前に準備してください：

| 項目 | 例 | 備考 |
|-----|-----|----------|
| コントロールパネルURL | https://www.onamae.com/domain/navi/login.html | |
| ログインID/パスワード | - | コントロールパネル用 |
| FTP接続情報 | ftp.your-server.com | |
| FTPユーザー名/パスワード | - | |
| ドメイン名 | http://your-domain.com | |
| MySQL接続情報 | - | データベース作成時に設定 |

### 2. HP2550の設定情報

| 項目 | 設定値 |
|-----|--------|
| サーバーURL | `http://your-domain.com/weather/` |
| パス | `data/report/` |
| PASSKEY | `test123`（初期値、後で変更推奨） |

### 3. 準備するツール

- **FTPクライアント**: FileZilla, WinSCP, Cyberduckなど
- **テキストエディタ**: メモ帳、VSCode、Sublime Textなど

## インストール手順

### Step 1: コントロールパネルにログイン

1. [お名前.comコントロールパネル](https://www.onamae.com/domain/navi/login.html)にアクセス
2. ログインID・パスワードを入力してログイン
3. 「レンタルサーバー」メニューを選択

### Step 2: FTP接続情報の確認

1. コントロールパネルで「FTP設定」メニューを選択
2. FTP接続情報を確認・記録：
   - FTPサーバー名
   - FTPユーザー名
   - FTPパスワード

### Step 3: FTPクライアントでサーバーに接続

**FileZilla使用例:**
1. FileZillaを起動
2. 新しいサイト作成：
   - プロトコル: FTP
   - ホスト: FTPサーバー名
   - ユーザー名: FTPユーザー名
   - パスワード: FTPパスワード
3. 「接続」をクリック

### Step 4: ディレクトリ構造作成

サーバー側（右側ペイン）で以下の構造を作成：

```
public_html/
└── weather/                     # ← この部分を作成
    ├── logs/                    # ← この部分を作成
    └── data/                    # ← この部分を作成
        └── report/              # ← この部分を作成
```

**手順:**
1. `public_html` ディレクトリに移動
2. 右クリック → 「ディレクトリを作成」→ `weather`
3. `weather` ディレクトリ内に `logs` と `data` を作成
4. `data` ディレクトリ内に `report` を作成

### Step 5: ファイルアップロード

ローカルファイルをサーバーの対応する場所にアップロード：

| ローカルファイル | アップロード先 |
|-----------------|---------------|
| `config.php` | `public_html/weather/config.php` |
| `models.php` | `public_html/weather/models.php` |
| `utils.php` | `public_html/weather/utils.php` |
| `health.php` | `public_html/weather/health.php` |
| `stations.php` | `public_html/weather/stations.php` |
| `latest.php` | `public_html/weather/latest.php` |
| `stats.php` | `public_html/weather/stats.php` |
| `.htaccess` | `public_html/weather/.htaccess` |
| `data/report/index.php` | `public_html/weather/data/report/index.php` |

**FTPアップロード手順:**
1. 左側ペイン（ローカル）でファイルを選択
2. 右クリック → 「アップロード」
3. 右側ペイン（サーバー）で正しい場所に配置されたか確認

### Step 6: ファイル権限設定

FTPクライアントで以下の権限を設定：

**権限設定:**
1. PHPファイル（*.php）: 644
2. ディレクトリ: 755 
3. ログディレクトリ（logs/）: 777（書き込み可能）

**FileZillaでの権限設定方法:**
1. ファイル/ディレクトリを右クリック
2. 「ファイル許可」または「File permissions」を選択
3. 数値を入力または チェックボックスで設定

### Step 7: データベース作成

#### 7.1 コントロールパネルからデータベース作成
1. お名前.comコントロールパネルで「データベース」メニューを選択
2. 「新規データベース作成」をクリック
3. 以下を設定：
   - データベース名: `weather_db`
   - ユーザー名: 任意（記録する）
   - パスワード: 任意（記録する）
4. 「作成」をクリック

#### 7.2 phpMyAdminへのアクセス
1. コントロールパネルで「phpMyAdmin」をクリック
2. 作成したMySQLユーザー名・パスワードでログイン
3. 左側メニューで `weather_db` を選択

#### 7.3 テーブル作成
1. phpMyAdminで「SQL」タブをクリック
2. ローカルの `Database/create_tables.sql` ファイルを開く
3. 内容を全てコピーしてpkiPMyAdminに貼り付け
4. 「実行」をクリック
5. 成功メッセージを確認

#### 7.4 サンプルデータ投入
1. 同じく「SQL」タブで
2. ローカルの `Database/sample_data.sql` ファイルを開く
3. 内容をコピーして貼り付け
4. 「実行」をクリック

#### 7.5 テーブル作成確認
左側メニューで以下を確認：
- `weather_station` テーブルが存在
- `weather_observation` テーブルが存在
- `weather_station` にサンプルデータ1件が登録済み

### Step 8: 設定ファイル編集

#### 8.1 config.php をダウンロード・編集
1. FTPクライアントで `public_html/weather/config.php` をローカルにダウンロード
2. テキストエディタで開く
3. 以下の部分を実際の値に変更：

```php
// データベース設定を実際の値に変更
define('DB_HOST', 'あなたのMySQLサーバーホスト名');
define('DB_PORT', 3306);
define('DB_USER', 'あなたが作成したMySQLユーザー名');
define('DB_PASS', 'あなたが設定したMySQLパスワード');
define('DB_NAME', 'weather_db');
```

#### 8.2 設定ファイルをアップロード
1. 編集した `config.php` をFTPで再アップロード
2. `public_html/weather/config.php` に上書き保存

### Step 9: 動作確認

#### 9.1 ヘルスチェック確認
Webブラウザで以下のURLにアクセス：

```
http://あなたのドメイン名/weather/health.php
```

**期待される応答:**
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

#### 9.2 ステーション一覧確認
```
http://あなたのドメイン名/weather/stations.php
```

**期待される応答:**
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

### Step 10: HP2550デバイス設定

#### 10.1 HP2550側の設定
HP2550本体またはスマートフォンアプリで以下を設定：

**カスタムサーバー設定:**
- プロトコル: HTTP
- サーバー: `http://あなたのドメイン名/weather/`
- パス: `data/report/`
- PASSKEY: `test123`

**送信間隔:**
- 推奨: 60秒間隔

#### 10.2 データ受信テスト
1. HP2550の手動送信機能を使用
2. データ送信を実行
3. ブラウザで最新データを確認：
   ```
   http://あなたのドメイン名/weather/latest.php?station_id=hp2550_main
   ```

#### 10.3 継続的な動作確認
定期的に以下をチェック：
- ヘルスチェックAPI (`/health.php`)
- ステーション一覧 (`/stations.php`) 
- 最新データ (`/latest.php?station_id=xxx`)

### Step 11: 独自PASSKEYの設定（セキュリティ向上）

#### 11.1 新しいPASSKEYのハッシュ値生成
一時的なPHPファイルを作成してハッシュ値を生成：

1. テキストエディタで以下の内容のファイルを作成：
   ```php
   <?php
   echo hash('sha256', 'your-new-passkey-2025');
   ?>
   ```

2. `hash.php` として `public_html/weather/` にアップロード
3. ブラウザで `http://あなたのドメイン名/weather/hash.php` にアクセス
4. 表示されたハッシュ値をコピー
5. `hash.php` を削除（セキュリティのため）

#### 11.2 データベース更新
phpMyAdminで以下のSQLを実行：

```sql
-- 既存ステーションのPASSKEY更新
UPDATE weather_station 
SET passkey_sha256 = '手順11.1で生成したハッシュ値'
WHERE station_id = 'hp2550_main';

-- 更新確認
SELECT station_id, name, passkey_sha256 FROM weather_station;
```

#### 11.3 HP2550設定更新
HP2550のPASSKEY設定を新しい値に変更

## 運用・保守

### 日常監視
ブラウザで以下を定期的にチェック：
- システム状態: `/health.php`
- データ蓄積状況: `/stations.php`
- 統計情報: `/stats.php`

### 定期メンテナンス

#### ログファイル確認（月1回程度）
FTPで以下をダウンロード・確認：
- `logs/weather_receiver.log`
- `logs/php_errors.log`

#### データベースバックアップ（週1回程度）
1. phpMyAdminにログイン
2. `weather_db` を選択
3. 「エクスポート」タブをクリック
4. 「実行」でSQLファイルをダウンロード

### トラブル時の対応

#### システム問題
1. `/health.php` で問題箇所を特定
2. `logs/` 内のログファイルを確認
3. 必要に応じて `config.php` 設定を確認

#### データベース問題
1. phpMyAdminで接続確認
2. テーブル構造・データを確認
3. 必要に応じてバックアップから復元

## よくある問題と解決策

| 問題 | 原因 | 解決策 |
|-----|-----|--------|
| 500エラー | PHPエラー | `logs/php_errors.log` 確認 |
| データベース接続エラー | config.php設定不正 | DB接続情報を再確認 |
| PASSKEYエラー | ハッシュ値不一致 | SHA-256ハッシュ値を再計算 |
| ファイルアクセス拒否 | 権限不正 | FTPで644/755に設定 |
| 空白画面表示 | 構文エラー | PHPファイルの構文確認 |

## インストール完了確認チェックリスト

- [ ] FTPクライアント接続確認
- [ ] ディレクトリ構造作成完了
- [ ] PHPファイルアップロード完了
- [ ] ファイル権限設定完了
- [ ] データベース・テーブル作成完了
- [ ] サンプルデータ投入完了
- [ ] config.php設定完了
- [ ] ヘルスチェックAPI応答確認 (`/health.php`)
- [ ] ステーション一覧API確認 (`/stations.php`)
- [ ] HP2550デバイス設定完了
- [ ] 実際のデータ受信確認 (`/latest.php`)

すべてのチェックが完了すれば、インストールは成功です。

## Python版との違い・メリット

- **設置時間**: 3-4時間 → **30分-1時間**
- **デーモン登録**: 必要 → **不要**
- **プロセス管理**: 必要 → **不要**
- **サービス自動起動**: 手動設定 → **Apache起動時に自動**
- **運用トラブル**: 多数 → **大幅に軽減**

---

**注意**: 本システムは継続的なデータ蓄積を行います。定期的にディスク容量とデータベース容量の監視を行ってください。お名前サーバーの標準環境で安定動作するため、Python版と比べて運用が大幅に簡単になります。