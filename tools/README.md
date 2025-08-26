# PASSKEYハッシュ生成ツール

## 概要

HP2550気象データ受信システムで使用するPASSKEYのSHA-256ハッシュ値を生成するツールです。

## ファイル構成

- `hash_passkey.php` - PHPスクリプト版（メイン）
- `hash_passkey.bat` - Windowsバッチファイル版（wrapper）
- `README.md` - このファイル

## 使用方法

### 方法1: PHPスクリプト直接実行

```bash
php hash_passkey.php "your-passkey-here"
```

### 方法2: Windowsバッチファイル実行

```cmd
hash_passkey.bat "your-passkey-here"
```

## 使用例

```bash
# 基本的な使用例
php hash_passkey.php "test123"

# 複雑なパスワードの例
php hash_passkey.php "my-weather-station-2025"
php hash_passkey.php "AMF-HP2550-SECRET-KEY"
```

## 出力例

```
PASSKEY ハッシュ生成結果
========================
入力PASSKEY: test123
SHA-256ハッシュ: ecd71870d1963316a97e3ac3408c9835ad8cf0f3c1bc703527c30265534f75ae

データベース登録用SQL:
----------------------
-- 新規ステーション追加の場合
INSERT INTO weather_station (
    station_id, passkey_sha256, name, model, stationtype, location, is_active
) VALUES (
    'your_station_id',
    'ecd71870d1963316a97e3ac3408c9835ad8cf0f3c1bc703527c30265534f75ae',
    'ステーション名',
    'HP2550_Pro_V1.5.8',
    'EasyWeatherV1.4.0',
    '設置場所',
    1
);

-- 既存ステーションのPASSKEY更新の場合
UPDATE weather_station
SET passkey_sha256 = 'ecd71870d1963316a97e3ac3408c9835ad8cf0f3c1bc703527c30265534f75ae'
WHERE station_id = 'your_station_id';
```

## 注意事項

1. **PHP要件**: PHP 7.0以上が必要です
2. **セキュリティ**: 生成したハッシュ値のコピー時は注意してください
3. **SQL実行**: station_id を実際の値に変更してからSQL実行してください
4. **文字エンコーディング**: PASSKEYには英数字・記号のみ使用推奨

## インストール手順書への追加

### Step XX: 独自PASSKEYの設定

#### XX.1 ハッシュ値生成
```bash
# toolsディレクトリに移動
cd tools

# PASSKEYのハッシュ値生成
php hash_passkey.php "your-new-passkey-2025"
```

#### XX.2 データベース更新
生成されたSQLをprtMyAdminで実行

#### XX.3 HP2550設定更新
HP2550のPASSKEY設定を新しい値に変更

## トラブルシューティング

### PHPが見つからないエラー
- PHPがインストールされていることを確認
- 環境変数PATHにPHPが追加されていることを確認

### 文字化けエラー
- コマンドプロンプトの文字コードをUTF-8に変更
- `chcp 65001` コマンドを実行

## セキュリティベストプラクティス

1. **強力なPASSKEY**: 最低12文字以上、英数字・記号混在
2. **一意性**: ステーション毎に異なるPASSKEY使用
3. **定期更新**: 6ヶ月〜1年毎の更新推奨
4. **記録管理**: PASSKEYは安全な場所に記録・保管