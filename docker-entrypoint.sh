#!/bin/sh
set -e

# Generate APP_KEY ถ้ายังไม่มี
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Migrate database
php artisan migrate --force

# Start server
exec php artisan serve --host=0.0.0.0 --port=8000
