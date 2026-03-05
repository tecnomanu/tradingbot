#!/bin/bash

cd /var/www/html

mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

# Wait for DB to be ready (up to 30s)
echo "Waiting for database..."
for i in $(seq 1 30); do
    php artisan db:show --no-interaction > /dev/null 2>&1 && break
    echo "  attempt $i/30..."
    sleep 1
done

php artisan migrate --force --no-interaction || echo "WARNING: migrate failed, continuing anyway"
php artisan config:cache  || true
php artisan route:cache   || true
php artisan view:cache    || true
php artisan storage:link 2>/dev/null || true

exec "$@"
