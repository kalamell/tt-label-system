#!/bin/bash
# ============================================================
# TikTok Label System — สำหรับ Mac
# รันครั้งแรก: chmod +x start.sh && ./start.sh
# รันครั้งต่อไป: ./start.sh
# ============================================================

set -e

echo "================================================"
echo "  TikTok Label System — Startup Script (Mac)"
echo "================================================"

# 1. ตรวจสอบ Docker
if ! command -v docker &> /dev/null; then
    echo ""
    echo "[!] ไม่พบ Docker — กรุณาติดตั้งก่อน:"
    echo "    https://www.docker.com/products/docker-desktop/"
    echo ""
    open "https://www.docker.com/products/docker-desktop/"
    exit 1
fi

# 2. ตรวจสอบว่า Docker daemon รันอยู่
if ! docker info &> /dev/null; then
    echo "[!] Docker ยังไม่ได้เปิด — กำลังเปิด Docker Desktop..."
    open -a "Docker"
    echo "    รอ Docker เริ่มต้น (30 วินาที)..."
    sleep 30
fi

# 3. สร้าง .env ถ้ายังไม่มี
if [ ! -f .env ]; then
    echo "[*] สร้างไฟล์ .env..."
    cp .env.example .env
fi

# 4. Build & Start containers
echo "[*] กำลังเริ่มระบบ..."
docker compose up -d --build

# 5. รอ DB พร้อม
echo "[*] รอฐานข้อมูลเริ่มต้น..."
sleep 15

# 6. Seed ข้อมูลเริ่มต้น (migrate รันใน entrypoint อัตโนมัติ)
echo "[*] Seed ข้อมูลเริ่มต้น..."
docker compose exec app php artisan db:seed --class=ProductSeeder --force 2>/dev/null || true

echo ""
echo "================================================"
echo "  เริ่มต้นสำเร็จ!"
echo "  เปิดเบราว์เซอร์: http://localhost:8080"
echo "================================================"
echo ""

# เปิดเบราว์เซอร์อัตโนมัติ
sleep 2
open "http://localhost:8080"
