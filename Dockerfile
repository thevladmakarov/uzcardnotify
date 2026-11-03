FROM php:8.3-cli-alpine

RUN apk add --no-cache curl-dev oniguruma-dev \
    && docker-php-ext-install curl mbstring

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

CMD php -S 0.0.0.0:$PORT -t public
