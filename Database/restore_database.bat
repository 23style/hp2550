@echo off

REM HP2550 Weather Database Restore Script for Windows (Shift-JIS version, no backquotes)

REM ===== Settings =====
set "DB_NAME=weather_db"
set "DB_USER=root"
set "DB_HOST=localhost"

echo ===========================================
echo HP2550 Weather Database Restore
echo ===========================================

REM ===== Argument / file check =====
if "%~1"=="" (
    echo Usage: %~nx0 ^<backup_file.sql^>
    echo.
    echo Available backup files:
    dir /b "weather_db_*.sql" 2>nul || echo No backup files found.
    echo.
    pause
    exit /b 1
)

set "BACKUP_FILE=%~1"

if not exist "%BACKUP_FILE%" (
    echo Error: Backup file "%BACKUP_FILE%" not found!
    echo.
    echo Available backup files:
    dir /b "weather_db_*.sql" 2>nul || echo No backup files found.
    pause
    exit /b 1
)

echo Database: %DB_NAME%
echo Backup file: %BACKUP_FILE%
echo Host: %DB_HOST%
echo User: %DB_USER%
echo.

echo  WARNING: This operation will:
echo    - DROP the existing "%DB_NAME%" database
echo    - Recreate the database from the backup file
echo    - ALL CURRENT DATA WILL BE LOST
echo.
set /p CONFIRM=Are you sure you want to continue? (yes/no): 
if /i not "%CONFIRM%"=="yes" (
    echo Operation cancelled.
    pause
    exit /b 0
)

echo.
set /p DB_PASS=Enter MySQL password for user "%DB_USER%": 

echo.
echo Testing MySQL connection...
mysql -h"%DB_HOST%" -u"%DB_USER%" -p"%DB_PASS%" -e "SELECT VERSION();"
if errorlevel 1 (
    echo Failed to connect to MySQL. Please check your credentials/host.
    pause
    exit /b 1
)

echo.
echo Step 1: Dropping existing database...
mysql -h"%DB_HOST%" -u"%DB_USER%" -p"%DB_PASS%" -e "DROP DATABASE IF EXISTS %DB_NAME%;"
if errorlevel 1 (
    echo Failed to drop database "%DB_NAME%".
    pause
    exit /b 1
)

echo Step 2: Creating new database...
mysql -h"%DB_HOST%" -u"%DB_USER%" -p"%DB_PASS%" -e "CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo Failed to create database "%DB_NAME%".
    pause
    exit /b 1
)

echo Step 3: Restoring data from backup...
mysql -h"%DB_HOST%" -u"%DB_USER%" -p"%DB_PASS%" --default-character-set=utf8mb4 "%DB_NAME%" < "%BACKUP_FILE%"
if errorlevel 1 (
    echo.
    echo Database restore failed!
    echo The database might be in an inconsistent state.
    echo Please check the backup file and try again.
    pause
    exit /b 1
)

echo.
echo Database restore completed successfully!

echo.
echo Database information (tables / approx rows):
mysql -h"%DB_HOST%" -u"%DB_USER%" -p"%DB_PASS%" --default-character-set=utf8mb4 ^
  -e "SELECT TABLE_NAME AS 'Table', TABLE_ROWS AS 'Rows' FROM information_schema.TABLES WHERE TABLE_SCHEMA = '%DB_NAME%' ORDER BY TABLE_NAME;"

echo.
echo Restored from: %BACKUP_FILE%
echo Database: %DB_NAME%
echo ===========================================
pause
