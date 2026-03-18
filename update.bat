@echo off
:: =============================================================
:: update.bat — ดึงโค้ดใหม่จาก GitHub (Windows)
:: วิธีใช้: ดับเบิลคลิกไฟล์นี้
:: =============================================================

set TOKEN=ใส่_TOKEN_ตรงนี้

:: ไปที่โฟลเดอร์ของไฟล์นี้เสมอ (สำคัญมาก)
cd /d %~dp0

set REPO=https://%TOKEN%@github.com/kalamell/tt-label-system.git

echo ==============================
echo  TikTok Label - Update System
echo ==============================
echo  Folder: %CD%
echo ==============================

:: ตรวจสอบ token
if "%TOKEN%"=="ใส่_TOKEN_ตรงนี้" (
    echo.
    echo [X] ยังไม่ได้ใส่ TOKEN
    echo     เปิดไฟล์นี้ด้วย Notepad แล้วแก้บรรทัด set TOKEN=
    echo.
    pause
    exit /b 1
)

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
if %ERRORLEVEL% neq 0 (
    echo.
    echo [X] Pull ไม่สำเร็จ -- ตรวจสอบ TOKEN หรือการเชื่อมต่อ
    git remote set-url origin https://github.com/kalamell/tt-label-system.git
    pause
    exit /b 1
)
echo ✓ Done

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
