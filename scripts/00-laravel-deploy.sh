#!/usr/bin/env bash

set -Eeuo pipefail

cd /var/www/html

# Render provides PORT at runtime. richarvey/nginx-php-fpm reads NGINX_PORT.
export NGINX_PORT="${PORT:-10000}"

composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

php artisan package:discover --ansi --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan migrate --force --no-interaction
