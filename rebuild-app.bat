@echo off
setlocal
REM ===========================================================
REM rebuild-app.bat - rebuild container app only (keep database)
REM Double-click to run. Database / orders are NOT deleted.
REM ===========================================================

REM go to this script's folder (quoted to support spaces in path)
cd /d "%~dp0"

echo ================================================
echo   TikTok Label System - Rebuild app (keep data)
echo ================================================
echo Folder: %CD%
echo.

REM check docker compose file exists here
if not exist "docker-compose.yml" (
    echo [X] docker-compose.yml NOT found in this folder.
    echo     Put this .bat in the project root next to docker-compose.yml
    echo.
    pause
    exit /b 1
)

REM check Docker is running
docker info >nul 2>&1
if errorlevel 1 (
    echo [X] Docker is not running. Open Docker Desktop first.
    echo.
    pause
    exit /b 1
)

echo [*] Building app container...
docker compose up -d --build app
if errorlevel 1 (
    echo [X] Build failed. Run: docker compose logs app
    echo.
    pause
    exit /b 1
)

echo [*] Clearing cache...
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan cache:clear

echo.
echo ================================================
echo   Done. Database kept. Open http://localhost:8080
echo ================================================
echo.

start "" http://localhost:8080
pause
