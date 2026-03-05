#!/bin/bash

cd /var/www/html

mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

# Clear any cached config from build time so runtime env vars are used
php artisan config:clear
php artisan cache:clear

# Wait for DB to be ready (up to 60s)
echo "Waiting for database connection..."
for i in $(seq 1 60); do
    php artisan db:show --no-interaction > /dev/null 2>&1 && echo "Database ready!" && break
    echo "  attempt $i/60..."
    sleep 2
done

php artisan migrate --force --no-interaction
php artisan storage:link 2>/dev/null || true

exec "$@"
