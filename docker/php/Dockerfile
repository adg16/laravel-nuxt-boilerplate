# syntax=docker/dockerfile:1
FROM php:8.5-fpm-alpine AS base

RUN set -eux; \
    apk add --no-cache icu-dev libzip-dev oniguruma-dev; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    docker-php-ext-install pdo_mysql bcmath zip intl pcntl; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini

WORKDIR /var/www/html

# --- dev: source is bind-mounted, `make setup` runs composer install ---
FROM base AS dev

CMD ["php-fpm"]

# --- prod: self-contained image, no bind mount ---
FROM base AS prod

COPY backend/ .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
