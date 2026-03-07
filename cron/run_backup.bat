@echo off
REM ═══════════════════════════════════════════════════════════════
REM  النسخ الاحتياطي التلقائي - School Manager Auto Backup
REM  يمكن جدولته في Windows Task Scheduler
REM ═══════════════════════════════════════════════════════════════

echo =============================================
echo   النسخ الاحتياطي التلقائي - مدرسة بعشيقة
echo   التاريخ: %date% الوقت: %time%
echo =============================================
echo.

REM تغيير المسار لمجلد المشروع
cd /d C:\xampp\htdocs\School-Manager\cron

REM تشغيل سكربت PHP
C:\xampp\php\php.exe auto_backup.php

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
