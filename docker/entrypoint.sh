#!/bin/sh
set -e

cd /var/www/html

if [ ! -f storage/app/.gitkeep ]; then
    mkdir -p storage/app/public \
             storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs
fi

chown -R www-data:www-data storage bootstrap/cache

# Run migrations on first boot (idempotent)
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true

exec "$@"
