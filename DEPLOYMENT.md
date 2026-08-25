# Deployment — Checkout Platform (Hostinger Ubuntu 24.04)

Stack: **Laravel 13**, PHP **8.4** FPM, Filament, MySQL 8.4, Redis 7, Nginx, Docker Compose. Queue workers use **`queue:work`** (Horizon is not installed).

## VPS requirements

- Ubuntu 24.04 LTS (Hostinger KVM VPS recommended)
- 2+ vCPU, 4 GB RAM minimum (8 GB for production traffic)
- 40 GB SSD
- Docker Engine 24+ and Docker Compose v2 plugin
- Domain pointed to VPS (or Cloudflare proxy)

## First-time installation

```bash
# As root or with sudo
apt update && apt upgrade -y
apt install -y ca-certificates curl git ufw

# Docker (official convenience script)
curl -fsSL https://get.docker.com | sh
usermod -aG docker "$USER"
newgrp docker

# Firewall — allow SSH, HTTP, HTTPS
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# Clone application
sudo mkdir -p /var/www && sudo chown "$USER:$USER" /var/www
cd /var/www
git clone <YOUR_REPO_URL> CheckoutPlatform
cd CheckoutPlatform

# Environment
cp .env.docker.example .env.docker
cp .env.production.example .env.production
nano .env.docker

# Generate APP_KEY (once)
docker compose --env-file .env.docker run --rm app php artisan key:generate --show

# Node.js 20+ (for Vite build)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

cd backend && npm install && npm run build && cd ..
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml up -d --build

docker compose --env-file .env.docker exec -T app composer install --no-dev --optimize-autoloader
docker compose --env-file .env.docker exec -T app php artisan migrate --force
docker compose --env-file .env.docker exec -T app php artisan storage:link --force
docker compose --env-file .env.docker exec -T app php artisan optimize
docker compose --env-file .env.docker restart worker scheduler

bash scripts/verify-deployment.sh
```

## Domain and Cloudflare SSL

1. **A record** `@` and `www` → VPS IP (proxied).
2. SSL mode: **Full (strict)** when origin is healthy on port 80.
3. `APP_URL=https://your-domain.example`, `TRUSTED_PROXIES=*` in `.env.docker`.
4. Use `docker-compose.prod.yml` (production Nginx + no public MySQL/Redis ports).

## Routine deploy

```bash
cd /var/www/CheckoutPlatform
export ENV_FILE=.env.docker
bash scripts/deploy.sh
```

## Database backup

```bash
docker exec checkout_mysql mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" checkout_platform \
  | gzip > "backup-$(date +%F).sql.gz"
```

## Queue and scheduler

- **Queue:** `checkout_worker` — `docker compose restart worker`
- **Scheduler:** `checkout_scheduler` — `docker compose restart scheduler`
- **Horizon:** not installed; use `queue:work` only

## Troubleshooting

| Symptom | Action |
|--------|--------|
| 502 | Check `checkout_app` logs and health |
| Missing CSS/JS | `cd backend && npm run build` |
| Stuck jobs | `docker exec checkout_worker supervisorctl status` |

Health: **`GET /up`**

## Rollback

```bash
git checkout <previous-sha>
cd backend && npm run build && cd ..
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.docker exec -T app php artisan migrate --force
docker compose --env-file .env.docker restart worker scheduler
```

## GitHub Actions

Set secrets: `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`. Workflow: `.github/workflows/deploy.yml`.
