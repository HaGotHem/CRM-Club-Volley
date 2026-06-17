FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier les fichiers de l'application
COPY . /var/www/html

# Installer les dépendances PHP
RUN composer install --no-interaction --optimize-autoloader --no-dev

COPY ./docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Donner les bons droits
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Démarrer Nginx en arrière-plan et PHP-FPM au premier plan
CMD nginx && php-fpm
