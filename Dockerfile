FROM php:8.2-fpm

WORKDIR /var/www

# Install system dependencies + Nginx
RUN apt-get update && apt-get install -y \
    git curl zip unzip libsqlite3-dev nginx supervisor \
    && docker-php-ext-install pdo_sqlite

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# ✅ Create SQLite database and set permissions
RUN mkdir -p database && \
    touch database/database.sqlite && \
    chown -R www-data:www-data database && \
    chmod -R 775 database

# Fix permissions for Laravel
RUN mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Copy Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Expose HTTP port
EXPOSE 80

# Run migrations
RUN php artisan migrate --force || true

# Pre-cache Laravel assets and storage link
RUN php artisan storage:link || true
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# Start PHP-FPM and Nginx
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
