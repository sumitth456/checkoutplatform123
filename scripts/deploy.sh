#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-.env.docker}"
COMPOSE=(docker compose --env-file "$ENV_FILE" -f docker-compose.yml -f docker-compose.prod.yml)

echo "==> Pull latest code"
if [ "${SKIP_GIT_PULL:-0}" != "1" ]; then
  git pull --ff-only
else
  echo "SKIP_GIT_PULL=1 — using current tree"
fi

cp .env.docker backend/.env 2>/dev/null || true

echo "==> Build frontend assets"
(
  cd backend
  if [ -f package-lock.json ]; then
    npm ci
  else
    npm install
  fi
  npm run build
)

echo "==> Build and start containers"
"${COMPOSE[@]}" pull --ignore-pull-failures
"${COMPOSE[@]}" build
"${COMPOSE[@]}" up -d

if ! grep -q '^APP_KEY=base64:' backend/.env 2>/dev/null; then
  echo 'APP_KEY=' >> backend/.env
  "${COMPOSE[@]}" exec -T app php artisan key:generate --force || true
  cp backend/.env .env.docker 2>/dev/null || true
  "${COMPOSE[@]}" up -d --force-recreate app worker scheduler nginx
fi

echo "==> Composer (production) inside app container"
"${COMPOSE[@]}" exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Laravel maintenance"
"${COMPOSE[@]}" exec -T app php artisan migrate --force --no-interaction
echo "==> Seed default admin and permissions (idempotent)"
"${COMPOSE[@]}" exec -T app php artisan db:seed --force --no-interaction
"${COMPOSE[@]}" exec -T app php artisan db:seed --class=AdminPanelPermissionSeeder --force --no-interaction
"${COMPOSE[@]}" exec -T app php artisan storage:link --force || true
"${COMPOSE[@]}" exec -T app php artisan optimize:clear
"${COMPOSE[@]}" exec -T app php artisan optimize
"${COMPOSE[@]}" restart app

echo "==> Restart queue and scheduler"
"${COMPOSE[@]}" restart worker scheduler

echo "==> Health checks"
"${COMPOSE[@]}" ps
USE_PROD_COMPOSE=1 bash "$ROOT/scripts/verify-deployment.sh"

echo "Deploy finished."
