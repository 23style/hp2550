@echo off
REM HP2550 Weather Database Simple Backup Script for Windows

set DB_NAME=weather_db
set DB_USER=root
set DB_HOST=localhost

REM ===== タイムスタンプ生成 (yyyyMMddHHmmss) =====
setlocal enabledelayedexpansion
for /f "tokens=2 delims==" %%i in ('wmic os get localdatetime /value') do set ldt=%%i
set TIMESTAMP=!ldt:~0,14!
endlocal & set TIMESTAMP=%TIMESTAMP%

set BACKUP_FILE=weather_db_%TIMESTAMP%.sql

echo ===========================================
echo HP2550 Weather Database Simple Backup
echo ===========================================
echo Database: %DB_NAME%
echo Backup file: %BACKUP_FILE%
echo Host: %DB_HOST%
echo User: %DB_USER%
echo.

set /p DB_PASS=Enter MySQL password for user '%DB_USER%': 

echo.
echo Starting backup...

mysqldump -u"%DB_USER%" -p"%DB_PASS%" "%DB_NAME%" > "%BACKUP_FILE%"

if %ERRORLEVEL% equ 0 (
    echo.
    echo Backup completed successfully!
    echo File: %BACKUP_FILE%
    echo Location: %CD%\%BACKUP_FILE%
    echo.
    echo To restore this backup, use:
    echo restore_database.bat %BACKUP_FILE%
) else (
    echo.
    echo Backup failed!
    echo Please check your MySQL credentials and database connection.
    if exist "%BACKUP_FILE%" del "%BACKUP_FILE%"
    echo Cleaned up empty backup file.
    pause
    exit /b 1
)

echo ===========================================
pause
