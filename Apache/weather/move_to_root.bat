@echo off
echo HP2550 Ecowitt標準パス対応: dataフォルダをルートレベルに移動

echo 現在の構造確認...
dir data\report\

echo.
echo 移動前の準備...
if not exist "..\data" (
    mkdir "..\data"
    echo dataフォルダを作成しました
)

if not exist "..\data\report" (
    mkdir "..\data\report"
    echo reportフォルダを作成しました
)

echo.
echo index.phpをコピー中...
copy "data\report\index.php" "..\data\report\index.php"

echo.
echo 必要ファイルをコピー中...
copy "config.php" "..\config.php"
copy "models.php" "..\models.php"
copy "utils.php" "..\utils.php"

echo.
echo ログフォルダを作成...
if not exist "..\logs" (
    mkdir "..\logs"
    echo logsフォルダを作成しました
)

echo.
echo 完了！新しい構造:
echo htdocs/
echo ├── config.php
echo ├── models.php  
echo ├── utils.php
echo ├── logs/
echo └── data/
echo     └── report/
echo         └── index.php
echo.
echo HP2550設定:
echo [IP/hostname]: 192.168.68.161
echo ポート: 80
echo パス指定: なし（または /data/report/ が自動補完される）
pause