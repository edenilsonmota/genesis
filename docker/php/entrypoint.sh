#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache --no-ansi
    php artisan route:cache --no-ansi
    php artisan view:cache --no-ansi
fi

if [ "${CREATE_STORAGE_LINK:-true}" = "true" ] && [ ! -L public/storage ]; then
    php artisan storage:link --no-ansi || true
fi

exec "$@"
