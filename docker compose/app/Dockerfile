FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY . /var/www/html

# Copier tous les fichiers PHP dans le conteneur
COPY ./backend/public/ /var/www/html/

COPY ./nginx/default.conf /etc/nginx/conf.d/default.conf

# Donner les bons droits
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD service nginx start && php-fpm
