FROM php:8.2-fpm

# Dépendances système + extensions PHP (PostgreSQL)
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Code de l'application (contexte de build = ./app/Backend)
COPY . /var/www/html

# Installation des dépendances PHP (composer.json est dans src/)
RUN cd src && composer install --no-dev --optimize-autoloader --no-interaction

# Configuration nginx : on remplace le site par défaut
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# php-fpm en arrière-plan + nginx au premier plan
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
