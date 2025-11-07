# Stage 1: build with composer
FROM php:8.2-cli AS builder

# dependencias del sistema para extensiones y composer
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zlib1g-dev libonig-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# configurar e instalar gd y pdo_mysql y zip
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql

# instala composer (usa la imagen oficial composer para copiar binario)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
# copiar solo lo necesario para cachear composer
COPY composer.json composer.lock ./

# ejecutar composer (ahora ext-gd está disponible)
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts

# Stage 2: runtime (Apache + PHP)
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev zlib1g-dev libonig-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# instalar extensiones runtime (gd, pdo_mysql, zip)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd

# habilita rewrite si lo necesitas
RUN a2enmod rewrite

WORKDIR /var/www/html

# copia archivos de la app
COPY . /var/www/html

# copia vendor desde el stage builder
COPY --from=builder /app/vendor /var/www/html/vendor

# permisos mínimos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80