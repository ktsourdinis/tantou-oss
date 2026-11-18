#!/usr/bin/env bash
set -euo pipefail

APP_CONTAINER="${TANTOU_APP_CONTAINER:-tantou_app}"
REVERB_CONTAINER="${TANTOU_REVERB_CONTAINER:-tantou_reverb}"
APP_USER="${TANTOU_DOCKER_USER:-1000:1000}"

echo "⚡ Updating Tantou (Human Capacity OS)..."

echo "1/6 Pulling latest changes from Git..."
git checkout -- public/build/ 2>/dev/null || true
git pull --ff-only

echo "2/6 Installing PHP Composer dependencies..."
docker exec -u "$APP_USER" "$APP_CONTAINER" composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "3/6 Compiling Vite frontend assets..."
if docker exec "$APP_CONTAINER" which npm >/dev/null 2>&1; then
    docker exec -u "$APP_USER" "$APP_CONTAINER" sh -c "npm ci --no-audit --no-fund && npm run build"
else
    docker run --rm -u "$APP_USER" -e npm_config_cache=/tmp/.npm -v "$(pwd):/app" -w /app node:20 sh -c "npm ci --no-audit --no-fund && npm run build"
fi

echo "4/6 Running database migrations..."
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan migrate --force

echo "5/6 Refreshing application caches..."
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan optimize:clear
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan config:cache
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan route:cache
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan view:cache

echo "6/6 Restarting queue workers and Reverb WebSocket server..."
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan queue:restart
docker restart "$REVERB_CONTAINER" >/dev/null 2>&1 || true

echo "✅ Tantou update complete!"
