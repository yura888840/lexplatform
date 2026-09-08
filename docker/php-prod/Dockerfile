# Backend-образ для Kubernetes: код внутри, JWT-ключи генерируются на старте если не смонтированы
FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache git unzip icu-dev libzip-dev postgresql-dev rabbitmq-c-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-install intl zip pdo_pgsql opcache \
 && pecl install amqp redis \
 && docker-php-ext-enable amqp redis \
 && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN { echo "opcache.enable=1"; echo "opcache.memory_consumption=256"; \
      echo "opcache.validate_timestamps=0"; echo "memory_limit=512M"; \
      echo "upload_max_filesize=25M"; echo "post_max_size=25M"; } > /usr/local/etc/php/conf.d/lex.ini

FROM base AS build
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/backend
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist
COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev

FROM base AS runtime
WORKDIR /var/www/backend
COPY --from=build /var/www/backend ./
COPY docker/php-prod/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && mkdir -p var config/jwt && chown -R www-data:www-data var config/jwt
USER www-data
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
