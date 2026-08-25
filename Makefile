.PHONY: up down build restart logs shell migrate seed key test worker-scale clean assets verify

assets:
	cd backend && npm install && npm run build

up:
	docker compose --env-file .env.docker up -d --build

down:
	docker compose down

build:
	docker compose --env-file .env.docker build --no-cache

restart:
	docker compose restart

logs:
	docker compose logs -f

logs-worker:
	docker compose logs -f worker scheduler

shell:
	docker compose exec app bash

artisan:
	docker compose exec app php artisan $(cmd)

migrate:
	docker compose exec app php artisan migrate --force

seed:
	docker compose exec app php artisan db:seed --force

key:
	docker compose exec app php artisan key:generate --show

test:
	docker compose exec app php artisan test

worker-scale:
	docker compose up -d --scale worker=$(n)

supervisor-status:
	docker compose exec worker supervisorctl status

verify:
	docker compose --env-file .env.docker ps
	docker exec checkout_app php artisan about --only=environment,cache,drivers,storage
	docker exec checkout_app test -f public/build/manifest.json && echo vite_ok
	docker exec checkout_worker supervisorctl status
	docker exec checkout_scheduler supervisorctl status

clean:
	docker compose down -v --remove-orphans
