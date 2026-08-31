FROM richarvey/nginx-php-fpm:3.1.6

WORKDIR /var/www/html

COPY . /var/www/html

ENV WEBROOT=/var/www/html/public \
    RUN_SCRIPTS=1 \
    REAL_IP_HEADER=1 \
    PHP_ERRORS_STDERR=1 \
    PORT=10000

RUN chmod +x /var/www/html/scripts/00-laravel-deploy.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 10000

CMD ["/start.sh"]
