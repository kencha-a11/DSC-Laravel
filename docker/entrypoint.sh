#!/bin/bash
set -e

echo "🚀 Starting Laravel Application..."

# ---------------------------
# Detect environment
# ---------------------------
ENVIRONMENT=${APP_ENV:-local}
echo "ℹ️  Environment: $ENVIRONMENT"

# ---------------------------
# Wait for PostgreSQL
# ---------------------------
if [ "$DB_CONNECTION" = "pgsql" ] && [ "$ENVIRONMENT" = "production" ]; then
    echo "⏳ Waiting for PostgreSQL..."
    DB_WAIT_TIMEOUT=120
    WAITED=0
    until PGPASSWORD="$DB_PASSWORD" psql "host=$DB_HOST port=$DB_PORT user=$DB_USERNAME dbname=$DB_DATABASE sslmode=require" -c '\q' 2>/dev/null; do
        sleep 2
        WAITED=$((WAITED+2))
        if [ $WAITED -ge $DB_WAIT_TIMEOUT ]; then
            echo "❌ Database not ready after $DB_WAIT_TIMEOUT seconds"
            exit 1
        fi
    done
    echo "✅ Database connected!"
else
    echo "⚠️  Skipping DB wait (using ${DB_CONNECTION:-sqlite})"
fi

# ---------------------------
# Run migrations safely
# ---------------------------
# Only migrate tables that haven't run
echo "🔹 Running migrations..."
php artisan migrate --force --no-interaction --verbose || {
    echo "⚠️ Migrations may have already run, skipping duplicate errors..."
}

# ---------------------------
# Run production seeders safely
# ---------------------------
php artisan db:seed --class=ProductionAccountSeeder --force --verbose || true

# ---------------------------
# Clear & cache config/routes/views
# ---------------------------
echo "🔹 Clearing and caching config/routes/views..."
php artisan config:clear --verbose
php artisan route:clear --verbose
php artisan view:clear --verbose
php artisan config:cache --verbose
php artisan route:cache --verbose
php artisan view:cache --verbose

# ---------------------------
# Set permissions
# ---------------------------
echo "🔹 Setting permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/database
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database

# ---------------------------
# Configure PHP-FPM to use TCP
# ---------------------------
echo "🔹 Configuring PHP-FPM..."
rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/zz-docker.conf

cat > /usr/local/etc/php-fpm.d/www.conf <<EOF
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 20
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.process_idle_timeout = 10s
pm.max_requests = 500
clear_env = no
catch_workers_output = yes
EOF

# ---------------------------
# Test PHP-FPM configuration
# ---------------------------
echo "🔹 Testing PHP-FPM configuration..."
php-fpm -t || { echo "❌ PHP-FPM configuration test failed"; exit 1; }

# ---------------------------
# Start supervisord
# ---------------------------
echo "✅ Starting services with supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
