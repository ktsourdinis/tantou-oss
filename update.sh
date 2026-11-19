#!/usr/bin/env bash
set -euo pipefail

APP_CONTAINER="${TANTOU_APP_CONTAINER:-tantou_app}"
REVERB_CONTAINER="${TANTOU_REVERB_CONTAINER:-tantou_reverb}"
APP_USER="${TANTOU_DOCKER_USER:-1000:1000}"

echo "⚡ Updating Tantou (Human Capacity OS)..."

echo "1/5 Pulling latest changes from Git..."
git checkout -- public/build/ 2>/dev/null || true
git pull --ff-only

echo "2/5 Installing PHP Composer dependencies..."
docker exec -u "$APP_USER" "$APP_CONTAINER" composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "3/5 Running database migrations..."
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan migrate --force

echo "4/5 Refreshing application caches..."
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan optimize:clear
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan config:cache
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan route:cache
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan view:cache

echo "5/5 Restarting queue workers and Reverb WebSocket server..."
docker exec -u "$APP_USER" "$APP_CONTAINER" php artisan queue:restart
docker restart "$REVERB_CONTAINER" >/dev/null 2>&1 || true

echo "✅ Tantou update complete!"
