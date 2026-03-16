#!/bin/sh
set -e

# สร้าง .env จาก .env.example ถ้ายังไม่มี
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate APP_KEY เสมอ แล้ว export ค่าใหม่เข้า shell environment
# (Docker inject APP_KEY= ว่างจาก env_file ทับ ต้อง override กลับ)
php artisan key:generate --force
APP_KEY=$(grep '^APP_KEY=' .env | cut -d'=' -f2-)
export APP_KEY

# Migrate database
php artisan migrate --force

# Start server
exec php artisan serve --host=0.0.0.0 --port=8000
