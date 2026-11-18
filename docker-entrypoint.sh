#!/usr/bin/env sh
set -e

# ==============================================================================
# ⚡ Tantou (担当) — Human Capacity OS
# Docker Auto-Bootstrap Entrypoint
# ==============================================================================

# 1. Ensure Laravel runtime directories exist and are writable
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# 2. First-boot dependency & asset auto-bootstrap
# Only the primary php-fpm container runs composer/npm install to avoid race conditions;
# secondary worker containers (queue, reverb, telegram_poll) wait until vendor/autoload.php is ready.
if [ "${1:-}" = "php-fpm" ] || [ -z "${1:-}" ]; then
    if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
        echo "⚡ [Tantou Entrypoint] /var/www/html/vendor/autoload.php not found. Running composer install..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    if [ ! -f "/var/www/html/public/build/manifest.json" ] && [ -f "/var/www/html/package.json" ]; then
        echo "⚡ [Tantou Entrypoint] /var/www/html/public/build/manifest.json not found. Building frontend assets..."
        npm ci --no-audit --no-fund && npm run build
    fi
else
    while [ ! -f "/var/www/html/vendor/autoload.php" ]; do
        echo "⏳ [Tantou Worker] Waiting for tantou_app to finish initial composer install..."
        sleep 2
    done
fi

exec "$@"
