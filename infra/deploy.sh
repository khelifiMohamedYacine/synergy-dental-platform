#!/bin/sh
# Deploy or update the platform on the server. Run ON the server from /opt/synergy:
#   sh infra/deploy.sh
# First time: clone the repo to /opt/synergy and place the production .env there first.
set -eu
cd "$(dirname "$0")/.."
[ -f .env ] || { echo "Missing /opt/synergy/.env (create it with infra/make_env.py)" >&2; exit 1; }
chmod 600 .env

C="docker compose -f docker-compose.prod.yml --env-file .env"

echo "== Pulling latest code"
git pull --ff-only

echo "== Building images"
$C build --pull

echo "== Starting databases, running migrations + seed, starting the API"
$C up -d postgres mariadb
$C up -d api          # waits for the one-off migrate service to finish successfully

echo "== Starting WordPress and the edge proxy"
$C up -d wordpress caddy

echo "== Provisioning WordPress + WooCommerce (idempotent)"
$C run --rm wpcli

echo "== Installing the nightly backup job"
CRON="15 3 * * * $(pwd)/infra/backup.sh >> /opt/synergy/backups/backup.log 2>&1"
( crontab -l 2>/dev/null | grep -v 'infra/backup.sh' ; echo "$CRON" ) | crontab -

echo "== Status"
$C ps --format 'table {{.Service}}\t{{.Status}}'
. ./.env
echo "Site:      https://$SITE_DOMAIN/"
echo "Dashboard: https://$SITE_DOMAIN/dashboard/"
echo "API docs:  https://$SITE_DOMAIN/api/v1/docs"
