@echo off
REM �^�X�N�X�P�W���[���[�o�^�p�Z�b�g�A�b�v�X�N���v�g
REM �Ǘ��Ҍ����Ŏ��s���Ă�������

echo ===================================================
echo HP2550 Daily Aggregation Task Scheduler Setup
echo ===================================================
echo.

set TASK_NAME=HP2550_Daily_Aggregation
set SCRIPT_PATH=%~dp0run_daily_aggregation.bat
set WORKING_DIR=%~dp0

echo Task Name: %TASK_NAME%
echo Script Path: %SCRIPT_PATH%
echo Working Directory: %WORKING_DIR%
echo.

REM �����^�X�N�̊m�F�E�폜
echo Checking for existing task...
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo Existing task found. Deleting...
    schtasks /delete /tn "%TASK_NAME%" /f
    if %ERRORLEVEL% equ 0 (
        echo Existing task deleted successfully.
    ) else (
        echo Failed to delete existing task.
        pause
        exit /b 1
    )
) else (
    echo No existing task found.
)
echo.

REM �V�����^�X�N�̍쐬
echo Creating new scheduled task...
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "\"%SCRIPT_PATH%\"" ^
    /sc daily ^
    /st 00:05 ^
    /sd %DATE% ^
    /ru "SYSTEM" ^
    /rl highest ^
    /f

if %ERRORLEVEL% equ 0 (
    echo.
    echo Task created successfully!
    echo.
    echo Task Details:
    echo   Name: %TASK_NAME%
    echo   Schedule: Daily at 00:05 (12:05 AM)
    echo   User: SYSTEM
    echo   Priority: Highest
    echo.
    
    REM �^�X�N�ڍו\��
    echo Verifying task...
    schtasks /query /tn "%TASK_NAME%" /fo LIST /v
    
    echo.
    echo ===================================================
    echo Setup completed successfully!
    echo ===================================================
    echo.
    echo The task will run automatically every day at 00:05.
    echo You can also run it manually using:
    echo   schtasks /run /tn "%TASK_NAME%"
    echo.
    echo To delete the task later, use:
    echo   schtasks /delete /tn "%TASK_NAME%" /f
    echo.
    
) else (
    echo.
    echo Failed to create task. Error code: %ERRORLEVEL%
    echo.
    echo Please check:
    echo 1. Run this script as Administrator
    echo 2. Ensure the script path is correct
    echo 3. Check Windows Task Scheduler service is running
    echo.
)

pause