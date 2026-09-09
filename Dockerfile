# Multi-stage production Dockerfile for ATLAS VOC Analysis on Railway
FROM php:8.4-cli-alpine AS base

# Install system dependencies & PHP extensions (PostgreSQL, SQLite, Zip, GD, BCMath)
RUN apk add --no-cache \
    postgresql-dev \
    sqlite-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    git \
    curl \
    nodejs \
    npm \
    bash

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pdo_sqlite \
        zip \
        gd \
        intl \
        bcmath \
        opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Copy Composer manifests & install dependencies (without scripts since artisan is not copied yet)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy NPM manifests & install packages
COPY package.json package-lock.json* vite.config.js tsconfig.json ./
RUN npm install --legacy-peer-deps --no-audit

# Copy application codebase
COPY . .

# Ensure .env exists from .env.example if not provided
RUN if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi

# Build frontend assets
RUN npm run build

# Run composer dump-autoload now that artisan and application code are present
RUN composer dump-autoload --optimize

# Ensure storage & bootstrap/cache permissions
RUN mkdir -p storage/framework/{sessions,views,cache} storage/app/temp_imports \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

ENV PORT=8000

# Start script running migrations and serving
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force \
    && php artisan db:seed --force \
    && php artisan serve --host=0.0.0.0 --port=$PORT
