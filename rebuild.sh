#!/bin/bash
# ลบ image เก่าทั้งหมดแล้ว build ใหม่
# รันครั้งแรก: chmod +x rebuild.sh && ./rebuild.sh

set -e

echo "================================================"
echo "  TikTok Label System — Rebuild (ลบ cache ทั้งหมด)"
echo "================================================"

if ! docker info &> /dev/null; then
    echo "[!] Docker ยังไม่ได้เปิด — กรุณาเปิด Docker Desktop ก่อน"
    exit 1
fi

# สร้าง .env บน host ถ้ายังไม่มี
if [ ! -f .env ]; then
    echo "[*] สร้างไฟล์ .env..."
    cp .env.example .env
fi

echo "[*] หยุดและลบ containers + images เก่า..."
docker compose down --rmi all

echo "[*] Build และเริ่มระบบใหม่..."
docker compose up -d --build

echo "[*] สร้าง directories ที่จำเป็น (uploads / labels)..."
mkdir -p public/uploads/originals \
         public/uploads/temp \
         public/uploads/pages \
         public/labels

echo "[*] รอฐานข้อมูลเริ่มต้น..."
sleep 15

echo "[*] รัน migrations..."
docker compose exec app php artisan migrate --force

echo "[*] Seed ข้อมูลเริ่มต้น..."
docker compose exec app php artisan db:seed --class=ProductSeeder --force 2>/dev/null || true

echo "[*] ล้าง cache..."
docker compose exec app php artisan config:clear 2>/dev/null || true
docker compose exec app php artisan view:clear 2>/dev/null || true

echo ""
echo "================================================"
echo "  Rebuild สำเร็จ!"
echo "  เปิดเบราว์เซอร์: http://localhost:8080"
echo "================================================"
echo ""

sleep 2
open "http://localhost:8080"
