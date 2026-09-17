#!/usr/bin/env bash
set -euo pipefail

cd /srv/genesis

git pull --ff-only

docker compose --env-file .env.production -f docker-compose.proxy.yml build
docker compose --env-file .env.production -f docker-compose.proxy.yml up -d --remove-orphans
docker compose --env-file .env.production -f docker-compose.proxy.yml exec -T app php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.proxy.yml exec -T app php artisan optimize
