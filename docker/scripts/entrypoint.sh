#!/bin/sh
set -e

echo "========================================="
echo "  NEXUS Application Startup"
echo "  Version: ${APP_VERSION:-unknown}"
echo "  Environment: ${APP_ENV:-production}"
echo "========================================="

# Wait for required services
wait_for_service() {
    local host=$1
    local port=$2
    local service=$3
    local max_attempts=${4:-30}
    local attempt=1

    echo "==> Waiting for $service at $host:$port..."

    while [ $attempt -le $max_attempts ]; do
        if nc -z "$host" "$port" 2>/dev/null; then
            echo "==> $service is ready!"
            return 0
        fi
        echo "    Attempt $attempt/$max_attempts - $service not ready..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "==> WARNING: $service not available after $max_attempts attempts"
    return 1
}

# Wait for MySQL if configured
if [ -n "$DB_HOST" ]; then
    wait_for_service "$DB_HOST" "${DB_PORT:-3306}" "MySQL" 30 || true
fi

# Wait for Redis if configured
if [ -n "$REDIS_HOST" ]; then
    wait_for_service "$REDIS_HOST" "${REDIS_PORT:-6379}" "Redis" 30 || true
fi

# Wait for Vault if configured
if [ -n "$VAULT_ADDR" ]; then
    VAULT_HOST=$(echo "$VAULT_ADDR" | sed 's|https\?://||' | cut -d: -f1)
    VAULT_PORT=$(echo "$VAULT_ADDR" | sed 's|https\?://||' | cut -d: -f2 | cut -d/ -f1)
    wait_for_service "$VAULT_HOST" "${VAULT_PORT:-8200}" "Vault" 10 || true
fi

# Run database migrations if enabled
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "==> Running database migrations..."
    php /var/www/html/scripts/migrations/run_all.php 2>/dev/null || echo "    Migration script not found or failed"
fi

# Clear and warm caches in production
if [ "$APP_ENV" = "production" ]; then
    echo "==> Clearing application caches..."
    php /var/www/html/scripts/cache/clear.php 2>/dev/null || true

    echo "==> Warming up caches..."
    php /var/www/html/scripts/cache/warmup.php 2>/dev/null || true
fi

# Fix permissions
echo "==> Setting permissions..."
chown -R nexus:nexus /var/www/html/logs 2>/dev/null || true
chown -R nexus:nexus /var/www/html/storage 2>/dev/null || true
chmod -R 775 /var/www/html/logs 2>/dev/null || true
chmod -R 775 /var/www/html/storage 2>/dev/null || true

# Create log directories if they don't exist
mkdir -p /var/log/php /var/log/nginx
chown -R nexus:nexus /var/log/php

# Verify PHP configuration
echo "==> Verifying PHP configuration..."
php -v
php -m | grep -E "(pdo_mysql|redis|opcache|mbstring)" || true

echo "========================================="
echo "  Application ready!"
echo "========================================="

# Execute the main command
exec "$@"
