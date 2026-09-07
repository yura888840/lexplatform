FROM php:8.4-fpm-alpine

RUN apk add --no-cache git unzip icu-dev libzip-dev postgresql-dev rabbitmq-c-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-install intl zip pdo_pgsql opcache \
 && pecl install amqp redis \
 && docker-php-ext-enable amqp redis \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.memory_consumption=256"; \
      echo "opcache.validate_timestamps=1"; \
      echo "memory_limit=512M"; \
      echo "upload_max_filesize=25M"; \
      echo "post_max_size=25M"; \
    } > /usr/local/etc/php/conf.d/lex.ini

WORKDIR /var/www/backend
CMD ["php-fpm"]
