# ============================================
# STAGE 1: Base PHP 8.2 with FPM + Extensions
# ============================================
FROM php:8.2-fpm AS base

# Set working directory
WORKDIR /var/www

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libsqlite3-dev \
    nginx \
    supervisor \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ============================================
# STAGE 2: Application Setup
# ============================================
FROM base AS app

# Copy project files (excluding .dockerignore files)
COPY . .

# Install PHP dependencies (production optimized)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

# ============================================
# STAGE 3: Laravel Setup & Permissions
# ============================================
FROM app AS laravel

# Create necessary directories
RUN mkdir -p \
    database \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Create SQLite database file
RUN touch database/database.sqlite

# Set proper permissions
RUN chown -R www-data:www-data \
    database \
    storage \
    bootstrap/cache \
    && chmod -R 775 \
    database \
    storage \
    bootstrap/cache

# Create storage link
RUN php artisan storage:link || true

# ============================================
# STAGE 4: Configuration
# ============================================

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copy Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Remove default Nginx config
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Expose port 80
EXPOSE 80

# ============================================
# Startup Script
# ============================================
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start services
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]