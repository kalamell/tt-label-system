@echo off
chcp 65001 >nul
REM ลบ image เก่าทั้งหมดแล้ว build ใหม่
REM ดับเบิลคลิก rebuild.bat

echo ================================================
echo   TikTok Label System — Rebuild (ลบ cache ทั้งหมด)
echo ================================================

docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] Docker ยังไม่ได้เปิด — กรุณาเปิด Docker Desktop ก่อน
    pause
    exit /b 1
)

REM สร้าง .env บน host ถ้ายังไม่มี
if not exist ".env" (
    echo [*] สร้างไฟล์ .env...
    copy .env.example .env >nul
)

echo [*] หยุดและลบ containers + images เก่า...
docker compose down --rmi all

echo [*] Build และเริ่มระบบใหม่...
docker compose up -d --build

echo [*] รอฐานข้อมูลเริ่มต้น...
timeout /t 15 /nobreak >nul

echo [*] Seed ข้อมูลเริ่มต้น...
docker compose exec app php artisan db:seed --class=ProductSeeder --force

echo.
echo ================================================
echo   Rebuild สำเร็จ!
echo   เปิดเบราว์เซอร์: http://localhost:8080
echo ================================================
echo.

timeout /t 2 /nobreak >nul
start http://localhost:8080

pause
