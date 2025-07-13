# Build stage
FROM composer:latest as build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy source code
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# Production stage
FROM php:8.3-fpm

# Install system dependencies and PHP extensions in one layer
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip \
    && printf "\n" | pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files from build stage
COPY --from=build /app /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/laravel.log \
    && chown -R www-data:www-data /var/www/html/storage/logs

# Switch to non-root user
USER www-data

# Expose port 9000
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]