FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates libonig-dev \
    && docker-php-ext-install pdo_mysql mysqli mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
RUN chown www-data:www-data config \
    && printf 'display_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/sentrix-production.ini

EXPOSE 80
