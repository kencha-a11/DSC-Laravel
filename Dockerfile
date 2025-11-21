# ============================================
# STAGE 1: Base PHP 8.2 FPM
# ============================================
FROM php:8.2-fpm AS base

WORKDIR /var/www

# Install system dependencies & PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl zip unzip libsqlite3-dev libpq-dev postgresql-client \
        nginx supervisor \
    && docker-php-ext-install pdo_sqlite pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer --version

# ============================================
# STAGE 2: Application Setup
# ============================================
FROM base AS app

COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ============================================
# STAGE 3: Laravel Setup & Permissions
# ============================================
FROM app AS laravel

RUN mkdir -p database storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && chown -R www-data:www-data database storage bootstrap/cache \
    && chmod -R 775 database storage bootstrap/cache \
    && php artisan storage:link || true

# ============================================
# STAGE 4: Configuration
# ============================================
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# ============================================
# STAGE 5: Entrypoint & Healthcheck
# ============================================
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
