#!/bin/sh
set -e

# สร้าง .env จาก .env.example ถ้ายังไม่มี
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate APP_KEY ถ้ายังไม่มี (เขียนลง .env)
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Clear config cache เพื่อให้รับค่า env ใหม่
php artisan config:clear

# Migrate database
php artisan migrate --force

# Start server
exec php artisan serve --host=0.0.0.0 --port=8000
