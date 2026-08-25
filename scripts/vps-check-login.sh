#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml)

"${COMPOSE[@]}" exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "users: ".App\Models\User::count().PHP_EOL;
$u = App\Models\User::where("email", "admin@checkoutplatform.test")->first();
if ($u) {
    echo "admin status=".$u->status.PHP_EOL;
} else {
    echo "no admin user".PHP_EOL;
}
'

COOKIE="$(mktemp)"
HTML="$(mktemp)"
trap 'rm -f "$COOKIE" "$HTML"' EXIT

curl -sS -c "$COOKIE" -b "$COOKIE" http://127.0.0.1:8080/admin/login -o "$HTML"
TOKEN="$(grep -oP 'name="csrf-token" content="\K[^"]+' "$HTML" | head -1 || true)"
echo "csrf token length: ${#TOKEN}"

curl -sS -c "$COOKIE" -b "$COOKIE" -X POST http://127.0.0.1:8080/admin/session-login \
  -H "Accept: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -F "email=admin@checkoutplatform.test" \
  -F "password=password" \
  -F "remember=0" \
  -F "_token=${TOKEN}" \
  -w "\nHTTP:%{http_code}\n"

echo "--- GET /admin after login ---"
curl -sS -c "$COOKIE" -b "$COOKIE" -o /dev/null -w "admin:%{http_code}\n" http://127.0.0.1:8080/admin
