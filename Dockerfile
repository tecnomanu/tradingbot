# ── Stage 1: Base image with all PHP extensions ───────────────────────
FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y \
        git curl zip unzip \
        libpng-dev libonig-dev libxml2-dev \
        libzip-dev libpq-dev \
        libjpeg62-turbo-dev libfreetype6-dev \
        default-mysql-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql pdo_pgsql pgsql gd bcmath mbstring zip opcache pcntl exif

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── Stage 2: Dependencies ─────────────────────────────────────────────
FROM base AS dependencies

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps

# ── Stage 3: Build ────────────────────────────────────────────────────
FROM dependencies AS build

ARG APP_NAME="GridBot Trading"
ENV VITE_APP_NAME=${APP_NAME}

COPY . .

RUN composer dump-autoload --optimize --no-dev

RUN npm run build

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# ── Stage 4: Production ───────────────────────────────────────────────
FROM base AS production

WORKDIR /var/www/html

COPY --from=build /var/www/html /var/www/html

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ── Service targets ───────────────────────────────────────────────────
FROM production AS web
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM production AS horizon
CMD ["php", "artisan", "horizon"]
