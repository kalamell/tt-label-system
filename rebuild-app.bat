@echo off
chcp 65001 >nul
setlocal enableextensions
:: =============================================================
:: rebuild-app.bat — rebuild เฉพาะ container app (ไม่ลบฐานข้อมูล)
:: ใช้ตอน "อัปโค้ดใหม่" ธรรมดา — ข้อมูล MySQL / ออเดอร์ ยังอยู่ครบ
:: ต่างจาก rebuild.bat ที่ลบ volume + docker\mysql (ล้างทุกอย่าง)
:: วิธีใช้: ดับเบิลคลิกไฟล์นี้
:: =============================================================

:: ไปที่โฟลเดอร์ของไฟล์นี้เสมอ
cd /d %~dp0

echo ================================================
echo   TikTok Label System — Rebuild app (เก็บข้อมูล)
echo ================================================

:: 1) ตรวจ Docker Desktop
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] Docker ยังไม่ได้เปิด — กรุณาเปิด Docker Desktop ก่อน
    pause
    exit /b 1
)

:: 2) build + start เฉพาะ service app (ไม่แตะ db / phpmyadmin / volume)
echo [*] Build และ restart container app...
docker compose up -d --build app
if %ERRORLEVEL% NEQ 0 (
    echo [!] Build ล้มเหลว — ตรวจ log: docker compose logs app
    pause
    exit /b 1
)

:: 3) ล้าง cache (กัน compiled view / config เก่าค้าง)
echo [*] ล้าง cache...
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan cache:clear

echo.
echo ================================================
echo   เสร็จแล้ว! (ฐานข้อมูลไม่ถูกลบ)
echo   เปิดเบราว์เซอร์: http://localhost:8080
echo ================================================
echo.

timeout /t 2 /nobreak >nul
start http://localhost:8080

pause
