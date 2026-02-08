FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip zip \
    && docker-php-ext-install pdo pdo_sqlite curl dom intl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/exports /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/data

EXPOSE 9000
CMD ["php-fpm"]
