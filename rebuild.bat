@echo off
chcp 65001 >nul
setlocal enableextensions
REM ลบ image + volume + MySQL data เก่า แล้ว build ใหม่หมด
REM ใช้ตอนย้ายเครื่อง หรือต้องการล้าง state ทั้งหมด
REM ดับเบิลคลิก rebuild.bat

echo ================================================
echo   TikTok Label System — Rebuild (ล้างทุกอย่าง)
echo ================================================

REM 1) ตรวจ Docker
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] Docker ยังไม่ได้เปิด — กรุณาเปิด Docker Desktop ก่อน
    pause
    exit /b 1
)

REM 2) สร้าง .env ถ้ายังไม่มี
if not exist ".env" (
    echo [*] สร้างไฟล์ .env...
    copy .env.example .env >nul
)

REM 3) หยุดและลบ containers + images + orphans
echo [*] หยุดและลบ containers + images เก่า...
docker compose down --rmi all --volumes --remove-orphans

REM 4) ลบโฟลเดอร์ MySQL data เก่า (แก้ปัญหา mysql.sock / auto.cnf จากเครื่องเดิม)
if exist "docker\mysql" (
    echo [*] ล้างข้อมูล MySQL เก่าใน docker\mysql ...
    rmdir /s /q "docker\mysql" 2>nul
    if exist "docker\mysql" (
        echo [!] บางไฟล์ลบจาก Windows ไม่ได้ — ใช้ container ลบแทน...
        docker run --rm -v "%CD%\docker:/work" alpine sh -c "rm -rf /work/mysql" >nul 2>&1
    )
)
mkdir "docker\mysql" 2>nul

REM 5) Build images และเริ่ม containers
echo [*] Build images และเริ่ม containers...
docker compose up -d --build
if %ERRORLEVEL% NEQ 0 (
    echo [!] Build ล้มเหลว — ตรวจ log: docker compose logs
    pause
    exit /b 1
)

REM 6) รอ DB ขึ้นสถานะ healthy แทนการ timeout คงที่
echo [*] รอฐานข้อมูลพร้อมรับการเชื่อมต่อ...
set /a tries=0
:wait_db
docker compose ps db 2>nul | findstr /i "healthy" >nul
if %ERRORLEVEL% EQU 0 goto db_ready
set /a tries+=1
if %tries% GEQ 60 (
    echo [!] DB ไม่ขึ้นสถานะ healthy ภายใน 2 นาที
    echo     ตรวจ log: docker compose logs db
    pause
    exit /b 1
)
timeout /t 2 /nobreak >nul
goto wait_db
:db_ready
echo [+] ฐานข้อมูลพร้อมใช้งาน

REM 7) รอให้ entrypoint ของ app รัน migrate เสร็จ
echo [*] รอให้แอป migrate ฐานข้อมูล...
timeout /t 5 /nobreak >nul

REM 8) Seed ข้อมูลเริ่มต้น
echo [*] Seed ข้อมูลเริ่มต้น...
docker compose exec -T app php artisan db:seed --class=ProductSeeder --force

echo.
echo ================================================
echo   Rebuild สำเร็จ!
echo   เปิดเบราว์เซอร์: http://localhost:8080
echo   phpMyAdmin:    http://localhost:8081
echo ================================================
echo.

timeout /t 2 /nobreak >nul
start http://localhost:8080

pause
