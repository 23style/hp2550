@echo off
REM HP2550 Daily Weather Aggregation - Task Scheduler用バッチファイル
REM 
REM 実行タイミング: 毎日 00:05
REM タスクスケジューラーで以下の設定で登録:
REM - トリガー: 毎日 午前 12:05
REM - 操作: このバッチファイルのフルパスを指定

REM ==================================================
REM 設定
REM ==================================================
set SCRIPT_DIR=%~dp0
set PHP_SCRIPT=%SCRIPT_DIR%daily_aggregation.php
set LOG_FILE=%SCRIPT_DIR%daily_aggregation_batch.log

REM 作業ディレクトリを変更
cd /d "%SCRIPT_DIR%"

REM ==================================================
REM ログ開始
REM ==================================================
echo. >> "%LOG_FILE%"
echo ================================================== >> "%LOG_FILE%"
echo Daily Aggregation Batch Started >> "%LOG_FILE%"
echo Date/Time: %DATE% %TIME% >> "%LOG_FILE%"
echo Script Dir: %SCRIPT_DIR% >> "%LOG_FILE%"
echo ================================================== >> "%LOG_FILE%"

REM ==================================================
REM PHP実行
REM ==================================================
echo Running daily aggregation script... >> "%LOG_FILE%"

REM PHPスクリプト実行（出力をログに追記）
php "%PHP_SCRIPT%" >> "%LOG_FILE%" 2>&1

REM ==================================================
REM 実行結果チェック
REM ==================================================
set PHP_EXIT_CODE=%ERRORLEVEL%

if %PHP_EXIT_CODE% equ 0 (
    echo Daily aggregation completed successfully. Exit code: %PHP_EXIT_CODE% >> "%LOG_FILE%"
    echo SUCCESS: Daily aggregation completed at %DATE% %TIME%
) else (
    echo Daily aggregation failed. Exit code: %PHP_EXIT_CODE% >> "%LOG_FILE%"
    echo ERROR: Daily aggregation failed at %DATE% %TIME%
    
    REM エラー時は30秒待ってから終了（ログ確認用）
    timeout /t 30 /nobreak > nul
)

echo ================================================== >> "%LOG_FILE%"
echo Daily Aggregation Batch Ended >> "%LOG_FILE%"
echo Final Exit Code: %PHP_EXIT_CODE% >> "%LOG_FILE%"
echo ================================================== >> "%LOG_FILE%"

exit /b %PHP_EXIT_CODE%