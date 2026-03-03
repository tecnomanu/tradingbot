# ── Stage 1: Frontend assets ──────────────────────────────────────────
FROM node:20-alpine AS node-builder
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --legacy-peer-deps
COPY vite.config.js tailwind.config.js postcss.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build

# ── Stage 2: PHP dependencies ────────────────────────────────────────
FROM composer:2 AS composer-builder
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ── Stage 3: Production image ────────────────────────────────────────
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql gd bcmath mbstring zip opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

COPY docker/php.ini        /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/nginx.conf     /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh  /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

COPY --from=composer-builder /build/vendor ./vendor
COPY . .
COPY --from=node-builder /build/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && rm -rf node_modules tests .env .env.* docker-compose.yml

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
