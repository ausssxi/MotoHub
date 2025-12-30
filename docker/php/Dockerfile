FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git

# Install PHP extensions (MySQL support)
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    bcmath

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

