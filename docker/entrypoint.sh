#!/bin/bash

cd /var/www/html

mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

# Only run migrations and setup on the web service (not horizon/workers)
if [ "$CONTAINER_ROLE" = "app" ] || [ -z "$CONTAINER_ROLE" ]; then
    php artisan config:clear 2>&1
    php artisan cache:clear 2>&1

    echo "Waiting for database..."
    for i in $(seq 1 60); do
        mysqladmin ping -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null && echo "DB ready!" && break
        echo "  attempt $i/60..."
        sleep 1
    done

    echo "Running migrations..."
    php artisan migrate --force --no-interaction 2>&1
    php artisan storage:link 2>/dev/null || true
fi

exec "$@"
