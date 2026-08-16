# Matcha — image PHP/Apache (Slim 4)
FROM php:8.3-apache

# Extensions nécessaires : PDO MySQL (base), GD (images), mbstring (Faker/Twig)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd mbstring \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Config PHP maison (taille upload, cookies de session sécurisés, fuseau)
COPY docker/php.ini /usr/local/etc/php/conf.d/matcha.ini

# DocumentRoot = public/ (bootstrap Slim + assets)
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
