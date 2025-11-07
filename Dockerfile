# Stage 1: build with composer
FROM php:8.2-cli AS builder

# dependencias del sistema para extensiones y composer
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zlib1g-dev libonig-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# instala composer (global)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
# copiar solo lo necesario para cachear composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts

# Stage 2: runtime (Apache + PHP)
FROM php:8.2-apache

# extensiones runtime
RUN apt-get update && apt-get install -y libzip-dev zlib1g-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# habilita rewrite y otros mods si necesitas
RUN a2enmod rewrite

WORKDIR /var/www/html

# copia archivos de la app
COPY . /var/www/html

# copia vendor desde el stage builder
COPY --from=builder /app/vendor /var/www/html/vendor

# permisos (ajusta según necesites)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exponer puerto (Railway detecta automáticamente, normalmente 8080 o 80)
EXPOSE 80

# Comando por defecto ya es start de apache en esta imagen
