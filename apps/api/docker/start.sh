#!/bin/sh
# Prepares the Laravel app on first start, then serves it.
set -e

cd /app

if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies (first run only)..."
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan db:seed --force

# Use PHP's built-in server directly rather than `php artisan serve`: artisan serve strips
# most environment variables (such as DB_HOST from docker-compose.yml) from request workers.
# Laravel's router script expects to run from the public directory.
cd /app/public
exec php -S 0.0.0.0:3001 /app/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
