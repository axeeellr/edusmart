# ---------- STAGE 1: composer builder (PHP 8.2) ----------
FROM php:8.2-cli AS composer-builder

# Variables para composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PATH=/root/.composer/vendor/bin:$PATH

WORKDIR /app

# Dependencias del sistema necesarias para extensiones o paquetes
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
 && rm -rf /var/lib/apt/lists/*

# Instala composer (si quieres usar la imagen oficial composer en lugar de esto, puedes)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar archivos necesarios para composer
# Primero composer.json/composer.lock para aprovechar cache
COPY composer.json composer.lock* /app/

# Si tu composer.json usa scripts que requieren más archivos, puede ser necesario copiar todo antes.
# Copiamos también plugins / archivos que puedan usarse en scripts de composer
COPY . /app

# Ejecutar composer install (prod)
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# ---------- STAGE 2: runtime PHP 8.2 + Apache ----------
FROM php:8.2-apache

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Paquetes de sistema para extensiones PHP en runtime (si se requieren)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libicu-dev \
    libxml2-dev \
 && rm -rf /var/lib/apt/lists/*

# Configurar y compilar extensiones PHP necesarias
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    mbstring \
    intl \
    xml \
    opcache

# Copiar composer por si se necesita en runtime (opcional)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar el código
COPY . /var/www/html

# Copiar vendor desde la etapa builder (ahora sí existe)
COPY --from=composer-builder /app/vendor /var/www/html/vendor
COPY --from=composer-builder /app/vendor-bin /var/www/html/vendor-bin 2>/dev/null || true

WORKDIR /var/www/html

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html

# Configurar Apache para permitir .htaccess
RUN printf '\n<Directory /var/www/html/>\n    AllowOverride All\n</Directory>\n' >> /etc/apache2/apache2.conf

EXPOSE 80
CMD ["apache2-foreground"]
