FROM php:8.2-fpm

WORKDIR /var/www

# Install dependencies including Nginx
RUN apt-get update && apt-get install -y \
    git curl zip unzip libsqlite3-dev nginx \
    && docker-php-ext-install pdo_sqlite

# Copy project files
COPY . .

# Install composer and dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Fix permissions
RUN mkdir -p storage/framework/views storage/framework/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/sites-available/default

# Expose port 80
EXPOSE 80

# Start both PHP-FPM and Nginx
CMD sh -c "php artisan storage:link || true && php-fpm -D && nginx -g 'daemon off;'"