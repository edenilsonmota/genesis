# syntax=docker/dockerfile:1.7

FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM php:8.4-fpm-bookworm AS app-base
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini

FROM app-base AS assets
COPY --from=node:22-bookworm-slim /usr/local /usr/local
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

FROM app-base AS app
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build

RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache /tmp/composer-cache \
    && chown -R www-data:www-data storage bootstrap/cache /tmp/composer-cache

ENV COMPOSER_HOME=/tmp/composer-cache
USER www-data
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx
WORKDIR /var/www/html
RUN apk add --no-cache openssl
COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
COPY --from=assets /var/www/html/public /var/www/html/public
RUN mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage
