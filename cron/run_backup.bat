@echo off
REM ═══════════════════════════════════════════════════════════════
REM  النسخ الاحتياطي التلقائي - School Manager Auto Backup
REM  يمكن جدولته في Windows Task Scheduler
REM ═══════════════════════════════════════════════════════════════

echo =============================================
echo   النسخ الاحتياطي التلقائي - Bashiqa School Manager
echo   التاريخ: %date% الوقت: %time%
echo =============================================
echo.

REM Run from this script directory (no hardcoded server path)
cd /d "%~dp0"

REM Prefer PHP_CLI from environment; otherwise "php" on PATH
if defined PHP_CLI (
    "%PHP_CLI%" auto_backup.php
) else (
    php auto_backup.php
)

REM التحقق من نجاح العملية
if %ERRORLEVEL% == 0 (
    echo.
    echo ✅ تم النسخ الاحتياطي بنجاح!
) else (
    echo.
    echo ❌ حدث خطأ أثناء النسخ الاحتياطي!
)

echo.
echo اضغط أي مفتاح للإغلاق...
pause > nul
