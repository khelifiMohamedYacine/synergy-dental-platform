#!/bin/bash
# Start the platform on a Render free web service (see deploy/render/Dockerfile).
#
# Required environment (render.yaml): DATABASE_URL (external PostgreSQL), JWT_SECRET,
# SERVICE_API_KEY, WP_SALT, WP_ADMIN_PASSWORD, SEED_ADMIN_PASSWORD.
# Render provides PORT and RENDER_EXTERNAL_URL.
set -euo pipefail
log() { echo "[start] $*"; }

: "${DATABASE_URL:?Set DATABASE_URL (PostgreSQL connection string)}"
: "${JWT_SECRET:?}" "${SERVICE_API_KEY:?}" "${WP_ADMIN_PASSWORD:?}" "${SEED_ADMIN_PASSWORD:?}"

export PORT="${PORT:-10000}"
export WP_URL="${RENDER_EXTERNAL_URL:-${WP_URL:-http://localhost:$PORT}}"
WP_URL="${WP_URL%/}"
case "$WP_URL" in https://*) export FORCE_HTTPS=1 ;; *) export FORCE_HTTPS=0 ;; esac

# PostgreSQL URLs for SQLAlchemy + psycopg 3 (providers hand out postgres:// or postgresql://).
# Neon: DATABASE_URL is the pooled endpoint (application traffic); DATABASE_URL_UNPOOLED is the
# direct endpoint, used for Alembic migrations (PgBouncer transaction mode breaks session state).
sqlalchemy_url() { local u="${1/#postgres:\/\//postgresql+psycopg://}"; echo "${u/#postgresql:\/\//postgresql+psycopg://}"; }
DATABASE_URL="$(sqlalchemy_url "$DATABASE_URL")"
MIGRATION_DATABASE_URL="$(sqlalchemy_url "${DATABASE_URL_UNPOOLED:-$DATABASE_URL}")"
export DATABASE_URL API_ENV=production CORS_ALLOWED_ORIGINS=""
export SEED_ADMIN_EMAIL="${SEED_ADMIN_EMAIL:-admin@example.test}" SEED_ADMIN_NAME="${SEED_ADMIN_NAME:-Administrator}"
export WP_ADMIN_USER="${WP_ADMIN_USER:-sd_site_owner}" WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-owner@example.test}"
export SYNERGY_API_URL="http://127.0.0.1:8000/api/v1" WC_BASE_URL="http://127.0.0.1:${PORT}"
# WooCommerce REST key for the API, derived from SERVICE_API_KEY so it is stable across restarts.
derive() { printf '%s' "$SERVICE_API_KEY:$1" | sha256sum | cut -c1-40; }
export WC_CONSUMER_KEY="ck_$(derive ck)" WC_CONSUMER_SECRET="cs_$(derive cs)"
export WP_SALT="${WP_SALT:-$JWT_SECRET}"

# ---------- business API (migrations are idempotent; the seed only fills an empty database) ----------
cd /opt/api
log "Migrating the business database..."
for i in 1 2 3 4 5; do
  DATABASE_URL="$MIGRATION_DATABASE_URL" /opt/venv/bin/alembic upgrade head && break
  log "Database not reachable yet (attempt $i), retrying..."; sleep 5
done
DATABASE_URL="$MIGRATION_DATABASE_URL" /opt/venv/bin/python -m app.seed
/opt/venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8000 --proxy-headers --forwarded-allow-ips '*' &

# ---------- WordPress (database baked into the image) ----------
php-fpm -F &
cd /var/www/html
wp eval-file /opt/render/runtime.php

# ---------- public entry point ----------
log "Serving $WP_URL on :$PORT"
XDG_DATA_HOME=/tmp/caddy XDG_CONFIG_HOME=/tmp/caddy caddy run --config /etc/caddy/Caddyfile --adapter caddyfile &
wait -n
log "A service stopped unexpectedly; exiting."
exit 1
