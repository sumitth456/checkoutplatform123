#!/bin/sh
set -e

cd /var/www/html

wait_for_service() {
    host="$1"
    port="$2"
    name="$3"
    attempts=30

    echo "Waiting for ${name} (${host}:${port})..."
    while [ "$attempts" -gt 0 ]; do
        if php -r "exit(@fsockopen('${host}', (int) '${port}') ? 0 : 1);"; then
            echo "${name} is ready."
            return 0
        fi
        attempts=$((attempts - 1))
        sleep 2
    done

    echo "Timed out waiting for ${name}."
    exit 1
}

if [ -n "${DB_HOST:-}" ]; then
    wait_for_service "${DB_HOST}" "${DB_PORT:-3306}" "MySQL"
fi

if [ -n "${REDIS_HOST:-}" ]; then
    wait_for_service "${REDIS_HOST}" "${REDIS_PORT:-6379}" "Redis"
fi

if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    if [ "${APP_ENV:-local}" = "production" ]; then
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    else
        composer install --no-interaction --prefer-dist
    fi
fi

if [ "${APP_ENV:-local}" != "production" ]; then
    echo "opcache.validate_timestamps=1" > /usr/local/etc/php/conf.d/opcache-override.ini
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "${APP_ENTRYPOINT_ROLE:-app}" = "worker" ]; then
    exec "$@"
fi

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

if [ "${STORAGE_LINK:-false}" = "true" ]; then
    php artisan storage:link --force 2>/dev/null || true
fi

exec "$@"
