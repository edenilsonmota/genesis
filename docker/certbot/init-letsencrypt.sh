#!/usr/bin/env sh
set -eu

: "${APP_DOMAIN:?Set APP_DOMAIN before running certbot init}"
: "${LETSENCRYPT_EMAIL:?Set LETSENCRYPT_EMAIL before running certbot init}"

docker compose --env-file .env.production -f docker-compose.prod.yml run --rm --entrypoint "\
  sh -c 'rm -rf \
    /etc/letsencrypt/live/${APP_DOMAIN} \
    /etc/letsencrypt/archive/${APP_DOMAIN} \
    /etc/letsencrypt/renewal/${APP_DOMAIN}.conf'" certbot

docker compose --env-file .env.production -f docker-compose.prod.yml run --rm --entrypoint "\
  certbot certonly --webroot \
  --webroot-path=/var/www/certbot \
  --email ${LETSENCRYPT_EMAIL} \
  --agree-tos \
  --no-eff-email \
  -d ${APP_DOMAIN}" certbot

docker compose --env-file .env.production -f docker-compose.prod.yml exec nginx nginx -s reload
