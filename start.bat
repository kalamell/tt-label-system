@echo off
chcp 65001 >nul
REM ============================================================
REM TikTok Label System — สำหรับ Windows
REM ดับเบิลคลิก start.bat เพื่อเริ่มระบบ
REM ============================================================

echo ================================================
echo   TikTok Label System — Startup Script (Windows)
echo ================================================

REM 1. ตรวจสอบ Docker
where docker >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [!] ไม่พบ Docker — กรุณาติดตั้งก่อน
    echo     กำลังเปิดหน้าดาวน์โหลด...
    start https://www.docker.com/products/docker-desktop/
    echo     หลังติดตั้ง Docker Desktop เสร็จแล้ว รันไฟล์นี้อีกครั้ง
    pause
    exit /b 1
)

REM 2. ตรวจสอบว่า Docker daemon รันอยู่
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] Docker ยังไม่ได้เปิด — กรุณาเปิด Docker Desktop แล้วรอสักครู่
    echo     จากนั้นรันไฟล์นี้อีกครั้ง
    pause
    exit /b 1
)

REM 3. สร้าง .env ถ้ายังไม่มี
if not exist ".env" (
    echo [*] สร้างไฟล์ .env...
    copy .env.example .env >nul
)

REM 4. Build & Start containers
echo [*] กำลังเริ่มระบบ (อาจใช้เวลาสักครู่ครั้งแรก)...
docker compose up -d --build

REM 5. รอ DB พร้อม
echo [*] รอฐานข้อมูลเริ่มต้น...
timeout /t 15 /nobreak >nul

REM 6. Setup ครั้งแรก
echo [*] ตั้งค่าแอปพลิเคชัน...
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=ProductSeeder --force

echo.
echo ================================================
echo   เริ่มต้นสำเร็จ!
echo   เปิดเบราว์เซอร์: http://localhost:8080
echo ================================================
echo.

REM เปิดเบราว์เซอร์อัตโนมัติ
timeout /t 2 /nobreak >nul
start http://localhost:8080

pause
