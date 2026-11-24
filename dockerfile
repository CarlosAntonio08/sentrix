FROM php:8.3-apache

RUN apt-get update && apt-get install -y libonig-dev \
    && docker-php-ext-install pdo_mysql mysqli mbstring \
    && a2enmod rewrite headers

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
