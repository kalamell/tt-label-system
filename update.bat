@echo off
:: =============================================================
:: update.bat — ดึงโค้ดใหม่จาก GitHub (Windows)
:: วิธีใช้: ดับเบิลคลิกไฟล์นี้
:: =============================================================

set TOKEN=
set REPO=https://%TOKEN%@github.com/kalamell/tt-label-system.git

echo ==============================
echo  TikTok Label - Update System
echo ==============================

:: ตรวจสอบ git
where git >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo.
    echo [X] ไม่พบ Git -- กรุณาติดตั้งก่อน
    echo.
    echo     ดาวน์โหลดได้ที่: https://git-scm.com/download/win
    echo     ติดตั้งเสร็จแล้วรีสตาร์ท แล้วลองใหม่
    echo.
    pause
    exit /b 1
)

for /f "tokens=3" %%i in ('git --version') do echo ✓ Git %%i

:: ตั้ง remote พร้อม token
git remote set-url origin %REPO%

:: ดึงโค้ดใหม่
echo.
echo [1/2] Pulling latest code...
git pull origin main
echo Done

:: ลบ token ออกจาก remote (ความปลอดภัย)
git remote set-url origin https://github.com/kalamell/tt-label-system.git

echo.
echo [2/2] Clearing Laravel cache...
php artisan config:clear
php artisan view:clear
php artisan cache:clear
echo Done

echo.
echo ==============================
echo  Update complete!
echo ==============================
echo.
pause
