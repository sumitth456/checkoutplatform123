# Checkout Platform

Laravel checkout platform with admin CRM features: orders, subscriptions, payments, affiliates, email, fulfillment, and landing pages.

## Stack

- Laravel 13 / PHP 8.3+
- Filament admin
- MySQL, Redis
- Docker Compose (Nginx, PHP-FPM, queue workers)
- Vite + Tailwind

## Layout

- `backend/` — Laravel app (modules live in `backend/app/Modules/`)
- `docker/` — PHP, Nginx, MySQL, Supervisor configs
- `scripts/` — deploy and VPS helpers
- `docker-compose.yml` / `docker-compose.prod.yml`

## Environment

Copy an example env file and fill in real values. Do not commit `.env` or `.env.docker`.

Local (Laravel):

```
cd backend
cp .env.example .env
```

Docker:

```
cp .env.docker.example .env.docker
```

Set at least `APP_KEY`, database credentials, and payment keys (`STRIPE_SECRET_KEY`, webhook secrets, etc.).

## Run

Local:

```
cd backend
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

Docker:

```
cp .env.docker.example .env.docker
docker compose --env-file .env.docker up -d --build
```

Admin is at `/admin`. See `DEPLOYMENT.md` for VPS/production notes.
