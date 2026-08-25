# Hostinger VPS — IP deploy (CloudPanel)

Target: **http://2.25.90.141:8080** (port **8080** avoids conflict with CloudPanel on **80**).

## Blockers resolved on your side

1. **SSH:** From this environment, `root@2.25.90.141` returns **Permission denied (publickey)** — no SSH private key is configured. Add your public key in Hostinger → VPS → SSH keys, or use **CloudPanel / hPanel → Browser terminal**.
2. **GitHub:** [sumitth456](https://github.com/sumitth456) has no **CheckoutPlatform** repository yet. Push this project to e.g. `sumitth456/CheckoutPlatform`, or upload the project via **SFTP** to `/var/www/CheckoutPlatform`.

## Option A — Git (after repo exists)

On the VPS:

```bash
apt update && apt install -y git
mkdir -p /var/www && cd /var/www
git clone https://github.com/sumitth456/CheckoutPlatform.git
cd CheckoutPlatform
chmod +x scripts/*.sh
cp .env.docker.vps.example .env.docker
nano .env.docker   # set DB passwords (replace CHANGE_ME_STRONG only)
bash scripts/vps-bootstrap-ip.sh
```

## Option B — SFTP upload

1. Zip `CheckoutPlatform` (exclude `backend/vendor`, `backend/node_modules`, `.env.docker`).
2. Upload to `/var/www/` and unzip.
3. Run the same `cp`, `nano`, and `bash scripts/vps-bootstrap-ip.sh` as above.

## Firewall (if site does not load)

```bash
ufw allow 8080/tcp
ufw allow OpenSSH
ufw status
```

## Verify

```bash
cd /var/www/CheckoutPlatform
USE_PROD_COMPOSE=1 APP_PORT=8080 bash scripts/verify-deployment.sh
curl -sf http://127.0.0.1:8080/up && echo OK
```

## Admin login (Filament)

After the first deploy, run (once) if you cannot sign in:

```bash
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml \
  exec -T app php artisan db:seed --force --no-interaction
```

Default credentials (change after first login):

| Field | Value |
|--------|--------|
| Email | `admin@checkoutplatform.test` |
| Password | `password` |

URL: **http://2.25.90.141:8080/admin/login**

## When you add a domain later

Set `APP_URL=https://your-domain`, `APP_PORT=80` (if CloudPanel is moved or proxied), enable HTTPS (Cloudflare or `scripts/setup-ssl-letsencrypt.sh`).

## Enable SSH from your PC (then we can deploy for you)

```powershell
ssh-keygen -t ed25519 -f $env:USERPROFILE\.ssh\hostinger -N '""'
Get-Content $env:USERPROFILE\.ssh\hostinger.pub
```

Add that public key on the VPS, then:

```powershell
ssh -i $env:USERPROFILE\.ssh\hostinger root@2.25.90.141
```

Send confirmation when SSH works and the Git repo URL exists; deployment can be completed remotely.
