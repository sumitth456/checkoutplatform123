#!/usr/bin/env bash
# Run on VPS as root after project files are in /var/www/CheckoutPlatform
# (git clone, SFTP upload, or rsync). No passwords echoed.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/CheckoutPlatform}"
cd "$APP_DIR"

COMPOSE=(docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml)

echo "==> Docker"
command -v docker >/dev/null || { curl -fsSL https://get.docker.com | sh; }
docker compose version

echo "==> Node (Vite)"
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

if [ ! -f .env.docker ]; then
  cp .env.docker.vps.example .env.docker
  echo "Created .env.docker — set MYSQL/DB passwords (CHANGE_ME_STRONG), then re-run."
  exit 1
fi

grep -q 'CHANGE_ME_STRONG' .env.docker && {
  echo "Replace CHANGE_ME_STRONG in .env.docker with strong passwords, then re-run."
  exit 1
}

cp .env.docker backend/.env
if ! grep -q '^APP_KEY=' backend/.env; then
  echo 'APP_KEY=' >> backend/.env
fi

echo "==> Vite build"
( cd backend && ( [ -f package-lock.json ] && npm ci || npm install ) && npm run build )

echo "==> Build app image"
"${COMPOSE[@]}" build app

echo "==> Composer dependencies on host mount (vendor/ for bind-mounted backend)"
"${COMPOSE[@]}" run --rm --no-deps --entrypoint composer app install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Compose up (production overrides)"
"${COMPOSE[@]}" up -d --build

if ! grep -q '^APP_KEY=base64:' backend/.env 2>/dev/null; then
  echo "==> Generate APP_KEY"
  "${COMPOSE[@]}" exec -T app php artisan key:generate --force
  cp backend/.env .env.docker
  "${COMPOSE[@]}" up -d --force-recreate app worker scheduler nginx
fi

echo "==> Laravel"
"${COMPOSE[@]}" exec -T app composer install --no-dev --optimize-autoloader
"${COMPOSE[@]}" exec -T app php artisan migrate --force
"${COMPOSE[@]}" exec -T app php artisan storage:link --force || true
"${COMPOSE[@]}" exec -T app php artisan optimize
"${COMPOSE[@]}" restart worker scheduler

USE_PROD_COMPOSE=1 bash scripts/verify-deployment.sh

echo "Done. Open: ${APP_URL:-http://127.0.0.1:8080}/ and ${APP_URL:-http://127.0.0.1:8080}/admin/login"
