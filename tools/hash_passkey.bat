@echo off
REM PASSKEY ハッシュ生成ツール (Windows バッチファイル版)
REM Usage: hash_passkey.bat [PASSKEY]

if "%1"=="" (
    echo PASSKEY ハッシュ生成ツール
    echo ================================
    echo.
    echo 使用方法:
    echo   hash_passkey.bat [PASSKEY]
    echo.
    echo 例:
    echo   hash_passkey.bat "test123"
    echo   hash_passkey.bat "my-secret-key-2025"
    echo.
    pause
    exit /b 1
)

REM PHPが利用可能かチェック
php -v >nul 2>&1
if errorlevel 1 (
    echo エラー: PHPが見つかりません。
    echo PHPをインストールするか、パスを通してください。
    echo.
    pause
    exit /b 1
)

REM ハッシュ生成実行
php "%~dp0hash_passkey.php" %1
echo.
pause