@echo off
REM HP2550 Daily Weather Aggregation - Task Scheduler�p�o�b�`�t�@�C��
REM 
REM ���s�^�C�~���O: ���� 00:05
REM �^�X�N�X�P�W���[���[�ňȉ��̐ݒ�œo�^:
REM - �g���K�[: ���� �ߑO 12:05
REM - ����: ���̃o�b�`�t�@�C���̃t���p�X���w��

REM ==================================================
REM �ݒ�
REM ==================================================
set SCRIPT_DIR=%~dp0
set PHP_SCRIPT=%SCRIPT_DIR%daily_aggregation.php
set LOG_FILE=%SCRIPT_DIR%daily_aggregation_batch.log

REM ��ƃf�B���N�g����ύX
cd /d "%SCRIPT_DIR%"

REM ==================================================
REM ���O�J�n
REM ==================================================
echo. >> "%LOG_FILE%"
echo ================================================== >> "%LOG_FILE%"
echo Daily Aggregation Batch Started >> "%LOG_FILE%"
echo Date/Time: %DATE% %TIME% >> "%LOG_FILE%"
echo Script Dir: %SCRIPT_DIR% >> "%LOG_FILE%"
echo ================================================== >> "%LOG_FILE%"

REM ==================================================
REM PHP���s
REM ==================================================
echo Running daily aggregation script... >> "%LOG_FILE%"

REM PHP�X�N���v�g���s�i�o�͂����O�ɒǋL�j
php "%PHP_SCRIPT%" >> "%LOG_FILE%" 2>&1

REM ==================================================
REM ���s���ʃ`�F�b�N
REM ==================================================
set PHP_EXIT_CODE=%ERRORLEVEL%

if %PHP_EXIT_CODE% equ 0 (
    echo Daily aggregation completed successfully. Exit code: %PHP_EXIT_CODE% >> "%LOG_FILE%"
    echo SUCCESS: Daily aggregation completed at %DATE% %TIME%
) else (
    echo Daily aggregation failed. Exit code: %PHP_EXIT_CODE% >> "%LOG_FILE%"
    echo ERROR: Daily aggregation failed at %DATE% %TIME%
    
    REM �G���[����30�b�҂��Ă���I���i���O�m�F�p�j
    timeout /t 30 /nobreak > nul
)

echo ================================================== >> "%LOG_FILE%"
echo Daily Aggregation Batch Ended >> "%LOG_FILE%"
echo Final Exit Code: %PHP_EXIT_CODE% >> "%LOG_FILE%"
echo ================================================== >> "%LOG_FILE%"

exit /b %PHP_EXIT_CODE%