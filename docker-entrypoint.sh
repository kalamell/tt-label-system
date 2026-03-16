#!/bin/sh
set -e

# สร้าง .env ใน container จาก .env.example
# (Laravel ต้องการไฟล์นี้บน disk — env vars จาก Docker ไม่พอ)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate APP_KEY และ export เข้า shell environment
php artisan key:generate --force
APP_KEY=$(grep '^APP_KEY=' .env | cut -d'=' -f2-)
export APP_KEY

# Migrate database
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port=8000
