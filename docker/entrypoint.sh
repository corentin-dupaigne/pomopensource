#!/bin/sh
set -e

# Generate APP_KEY if not provided
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY not set — generating one..."
    export APP_KEY=$(php artisan key:generate --show --no-ansi)
    echo "Generated APP_KEY (add it to your environment to make it permanent)"
fi

# Wait for the database
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
    until nc -z "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null; do
        sleep 1
    done
    echo "Database is ready."
fi

# Run migrations and seed settings
php artisan migrate --force
php artisan db:seed --class=SettingsSeeder --force

# Ensure SQLite database file (if used) is writable by www-data
chown -R www-data:www-data database/ 2>/dev/null || true

# Create public storage symlink
php artisan storage:link --force 2>/dev/null || true

# Cache for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
