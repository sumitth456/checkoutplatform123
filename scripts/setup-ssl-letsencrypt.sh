#!/usr/bin/env bash
# Optional origin TLS with Let's Encrypt (no Cloudflare proxy).
# Usage on VPS: DOMAIN=checkout.example.com EMAIL=you@example.com bash scripts/setup-ssl-letsencrypt.sh
# Requires: domain A record → this server, ports 80/443 open, APP already on port 80 (docker-compose.prod.yml).
set -euo pipefail

DOMAIN="${DOMAIN:?Set DOMAIN=your.domain}"
EMAIL="${EMAIL:?Set EMAIL=your@email}"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v certbot >/dev/null 2>&1; then
  echo "Installing certbot..."
  sudo apt-get update
  sudo apt-get install -y certbot
fi

# Temporarily stop nginx container so certbot can bind :80 (standalone)
docker compose --env-file .env.docker stop nginx

sudo certbot certonly --standalone -d "$DOMAIN" --agree-tos -m "$EMAIL" --non-interactive

CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"
mkdir -p docker/nginx/ssl
sudo cp "${CERT_DIR}/fullchain.pem" docker/nginx/ssl/fullchain.pem
sudo cp "${CERT_DIR}/privkey.pem" docker/nginx/ssl/privkey.pem
sudo chown "$(whoami):$(whoami)" docker/nginx/ssl/*.pem

echo "Certificates copied to docker/nginx/ssl/. Enable HTTPS in docker-compose.prod.yml (see DEPLOYMENT.md SSL section)."
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml up -d nginx
