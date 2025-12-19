# Production-ready Dockerfile for Laravel Application

FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    g++ \
    gcc \
    git \
    icu-dev \
    icu-libs \
    icu-libs \
    icu-dev \
    icu-libs \
    imagemagick \
    imagemagick-dev \
    libc-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    make \
    mysql-client \
    oniguruma-dev \
    rsync \
    zlib-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        gd \
        intl \
        mysqli \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js and npm dependencies
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && npm ci --only=production \
    && npm run build

# Create necessary directories and set permissions
RUN mkdir -p /var/www/storage/framework/{cache,sessions,views} \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Generate application key
RUN php artisan key:generate --force

# Cache configuration
RUN php artisan config:cache --force \
    && php artisan route:cache --force \
    && php artisan view:cache --force \
    && php artisan event:cache --force

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/api/ping || exit 1

# Start server
CMD php artisan serve --host=0.0.0.0 --port=8000
