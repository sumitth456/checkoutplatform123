#!/usr/bin/env bash
# First-time Hostinger/Ubuntu bootstrap — run ON THE VPS after cloning the repo.
# Does not echo secrets. Edit .env.docker manually before running deploy.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Checking Docker"
if ! command -v docker >/dev/null 2>&1; then
  echo "Docker not found. Install: curl -fsSL https://get.docker.com | sh"
  exit 1
fi
docker compose version

echo "==> Checking Node (for Vite)"
if ! command -v node >/dev/null 2>&1; then
  echo "Node.js not found. Install Node 20+ (see DEPLOYMENT.md), then re-run."
  exit 1
fi
node -v
npm -v

if [ ! -f .env.docker ]; then
  echo "==> Create .env.docker from example (edit secrets locally — do not commit)"
  cp .env.docker.example .env.docker
  echo "Edit .env.docker: APP_URL, APP_KEY, database passwords, Stripe, APP_PORT=80"
  echo "Generate APP_KEY: docker compose --env-file .env.docker run --rm app php artisan key:generate --show"
  exit 1
fi

export ENV_FILE=.env.docker
bash "$ROOT/scripts/deploy.sh"
