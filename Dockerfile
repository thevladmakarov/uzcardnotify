FROM php:8.3-cli-alpine

# Устанавливаем системные зависимости и расширения PHP
RUN apk add --no-cache curl-dev oniguruma-dev \
    && docker-php-ext-install curl mbstring

# Подтягиваем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Ставим зависимости без dev-пакетов с оптимизацией автолоадера
RUN composer install --no-dev --optimize-autoloader

# Запуск встроенного веб-сервера на порту от Render
CMD php -S 0.0.0.0:$PORT -t public