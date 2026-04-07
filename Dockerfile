# Stage 1: Build frontend assets
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci --prefer-offline
COPY . .
RUN npm run build

# Stage 2: Install PHP dependencies
FROM php:8.2-alpine AS deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Stage 3: Final image
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        netcat-openbsd \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql zip gd opcache \
    && rm -rf /tmp/*

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

# Copy app source (vendor/ and public/build/ excluded via .dockerignore)
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=deps /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
