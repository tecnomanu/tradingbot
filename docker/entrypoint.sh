#!/bin/bash

cd /var/www/html

mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

# Clear build-time config so runtime env vars are used
php artisan config:clear 2>&1
php artisan cache:clear 2>&1

# Wait for DB (up to 60s)
echo "Waiting for database connection..."
for i in $(seq 1 30); do
    php artisan db:show --no-interaction > /dev/null 2>&1 && echo "Database ready after ${i}s!" && break
    echo "  attempt $i/30..."
    sleep 2
done

echo "Running migrations..."
php artisan migrate --force --no-interaction 2>&1
echo "Migrations done."

php artisan storage:link 2>/dev/null || true

exec "$@"
