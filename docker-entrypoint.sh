#!/bin/sh
set -e

# เขียน .env ใหม่ทั้งไฟล์จาก Docker env vars โดยตรง
# ไม่ใช้ sed เพราะ .env.example อาจมี Windows line endings
cat > .env << EOF
APP_NAME="TikTok Label System"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL:-http://localhost:8080}

DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-tiktok_label}
DB_USERNAME=${DB_USERNAME:-tiktok}
DB_PASSWORD=${DB_PASSWORD:-secret123}

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LOG_CHANNEL=stack
LOG_LEVEL=debug
EOF

# Generate APP_KEY และ export เข้า shell environment
php artisan key:generate --force
APP_KEY=$(grep '^APP_KEY=' .env | cut -d'=' -f2-)
export APP_KEY

# Migrate database
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port=8000
