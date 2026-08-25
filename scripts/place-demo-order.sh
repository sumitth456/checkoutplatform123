#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-.env.docker}"
COMPOSE=(docker compose --env-file "$ENV_FILE" -f docker-compose.yml -f docker-compose.prod.yml)

EMAIL="${1:-sumit@test.com}"

echo "==> Place demo order (landing → checkout → active subscription)"
"${COMPOSE[@]}" exec -T app php artisan checkout:place-demo-order --email="$EMAIL"
