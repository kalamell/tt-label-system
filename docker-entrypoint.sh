#!/bin/sh
set -e

# สร้าง .env ใน container จาก .env.example
if [ ! -f .env ]; then
    cp .env.example .env
fi

# เขียน env vars จาก Docker ลงไฟล์ .env
# เพราะ Laravel อ่านไฟล์นี้บน disk เป็นหลัก
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=${DB_CONNECTION:-mysql}|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST:-db}|" .env
sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT:-3306}|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-tiktok_label}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-tiktok}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-}|" .env
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=${SESSION_DRIVER:-file}|" .env
sed -i "s|^CACHE_STORE=.*|CACHE_STORE=${CACHE_STORE:-file}|" .env

# Generate APP_KEY และ export เข้า shell environment
php artisan key:generate --force
APP_KEY=$(grep '^APP_KEY=' .env | cut -d'=' -f2-)
export APP_KEY

# Migrate database
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port=8000
