FROM serversideup/php:8.3-fpm-nginx AS vendor

USER root

RUN install-php-extensions gd \
    && mkdir -p /var/www/html \
    && chown www-data:www-data /var/www/html

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./

USER www-data

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM serversideup/php:8.3-fpm-nginx

USER root

RUN install-php-extensions gd

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor

ENV NGINX_WEBROOT=/var/www/html/public \
    SSL_MODE=off \
    PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_CONFIG_CACHE=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=true \
    AUTORUN_LARAVEL_VIEW_CACHE=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    APP_ENV=production \
    APP_DEBUG=false \
    PORT=8080

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data

EXPOSE 8080
