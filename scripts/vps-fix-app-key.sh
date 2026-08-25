#!/usr/bin/env bash
set -euo pipefail
cd /var/www/CheckoutPlatform
sed -i '/base64:/d' .env.docker
sed -i '/^APP_KEY=/d' .env.docker
KEY="$(docker run --rm -v /var/www/CheckoutPlatform/backend:/var/www/html --entrypoint php checkoutplatform-app:latest artisan key:generate --show)"
printf 'APP_KEY=%s\n' "$KEY" >> .env.docker
cp .env.docker backend/.env
COMPOSE=(docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml)
"${COMPOSE[@]}" up -d --force-recreate app worker scheduler nginx
"${COMPOSE[@]}" exec -T app php artisan optimize
curl -sf -o /dev/null -w "admin:%{http_code}\n" http://127.0.0.1:8080/admin/login
