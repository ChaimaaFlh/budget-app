FROM php:8.3-cli

# Dépendances système + extension PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    curl \
    git \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer (copié depuis l'image officielle Composer)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html