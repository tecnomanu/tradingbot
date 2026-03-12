#!/bin/bash

cd /var/www/html

mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

# DB readiness is guaranteed by depends_on: condition: service_healthy in compose.
if [ "$CONTAINER_ROLE" = "app" ] || [ -z "$CONTAINER_ROLE" ]; then
    php artisan config:clear 2>&1
    php artisan cache:clear 2>&1
    echo "Running migrations..."
    php artisan migrate --force --no-interaction 2>&1
    php artisan storage:link 2>/dev/null || true
fi

# Wait for Redis DNS before starting Horizon (Docker network may not be ready yet)
if [ "$CONTAINER_ROLE" = "horizon" ]; then
    echo "Waiting for Redis..."
    for i in $(seq 1 15); do
        php -r "try { \$r = new Redis(); \$r->connect(getenv('REDIS_HOST') ?: 'redis', (int)(getenv('REDIS_PORT') ?: 6379)); exit(0); } catch(\Throwable \$e) { exit(1); }" 2>/dev/null && echo "Redis ready!" && break
        echo "  attempt $i/15..."
        sleep 2
    done
fi

exec "$@"
