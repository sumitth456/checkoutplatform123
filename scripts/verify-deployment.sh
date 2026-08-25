#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-.env.docker}"
COMPOSE=(docker compose --env-file "$ENV_FILE" -f docker-compose.yml)
if [ -f docker-compose.prod.yml ] && [ "${USE_PROD_COMPOSE:-0}" = "1" ]; then
  COMPOSE+=( -f docker-compose.prod.yml )
fi

fail=0
check() {
  if "$@"; then
    echo "OK  $*"
  else
    echo "FAIL $*"
    fail=1
  fi
}

echo "=== Docker services ==="
"${COMPOSE[@]}" ps

echo "=== Container health ==="
for c in checkout_nginx checkout_app checkout_mysql checkout_redis checkout_worker checkout_scheduler; do
  if docker inspect --format='{{.State.Health.Status}}' "$c" 2>/dev/null | grep -q healthy; then
    echo "OK  health $c"
  elif docker inspect --format='{{.State.Status}}' "$c" 2>/dev/null | grep -q running; then
    if [ "$c" = "checkout_nginx" ] && ! docker inspect "$c" --format='{{.State.Health}}' 2>/dev/null | grep -q Status; then
      echo "OK  running $c (no healthcheck defined)"
    else
      echo "FAIL health $c"
      fail=1
    fi
  else
    echo "FAIL missing or stopped $c"
    fail=1
  fi
done

echo "=== HTTP ==="
check curl -sf -o /dev/null "http://127.0.0.1:${APP_PORT:-8080}/"
check curl -sf -o /dev/null "http://127.0.0.1:${APP_PORT:-8080}/up"
check curl -sf -o /dev/null "http://127.0.0.1:${APP_PORT:-8080}/admin/login"

echo "=== Laravel (app container) ==="
check docker exec checkout_app php artisan about --only=environment,cache,drivers,storage

echo "=== MySQL ==="
check docker exec checkout_app php artisan db:show --database=mysql

echo "=== Redis ==="
check docker exec checkout_redis redis-cli ping
check docker exec checkout_app php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo Illuminate\Support\Facades\Redis::connection()->ping();"

echo "=== Vite build ==="
check docker exec checkout_app test -f public/build/manifest.json

echo "=== Queue / scheduler ==="
check docker exec checkout_worker supervisorctl status
check docker exec checkout_scheduler supervisorctl status

echo "=== Storage ==="
check docker exec checkout_app test -L public/storage

exit "$fail"
