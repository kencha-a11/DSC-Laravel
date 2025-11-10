#!/bin/bash

# ============================================
# Docker Entrypoint Script for Laravel
# ============================================

set -e

echo "🚀 Starting Laravel Application..."

# ============================================
# 1. Wait for database (if using external DB)
# ============================================
# Uncomment if using PostgreSQL/MySQL
# echo "⏳ Waiting for database connection..."
# until php artisan db:show 2>/dev/null; do
#     echo "Database not ready, waiting..."
#     sleep 2
# done
# echo "✅ Database connected!"

# ============================================
# 2. Run Database Migrations
# ============================================
echo "🔄 Running database migrations..."
php artisan migrate --force --no-interaction || {
    echo "⚠️ Migration failed, but continuing..."
}

# ============================================
# 3. Clear and Cache Configuration
# ============================================
echo "🧹 Clearing old caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "💾 Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ============================================
# 4. Set Proper Permissions
# ============================================
echo "🔐 Setting file permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/database
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database

# ============================================
# 5. Start PHP-FPM
# ============================================
echo "🐘 Starting PHP-FPM..."
php-fpm -D

# ============================================
# 6. Start Nginx
# ============================================
echo "🌐 Starting Nginx..."
nginx -t && nginx -g 'daemon off;' || {
    echo "❌ Nginx configuration test failed!"
    exit 1
}