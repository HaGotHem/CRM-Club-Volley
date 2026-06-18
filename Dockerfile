FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    libpq-dev \
    postgresql-client \
    && docker-php-ext-install pdo pdo_pgsql opcache

# Activer et configurer OPcache (accélère fortement le bootstrap PHP)
COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier les fichiers de l'application
COPY . /var/www/html

# Installer les dépendances PHP
RUN composer install --no-interaction --optimize-autoloader

COPY ./docker/nginx/default.conf /etc/nginx/conf.d/default.conf

RUN rm /etc/nginx/sites-enabled/default

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD nginx && php-fpm

