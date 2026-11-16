#!/bin/bash
# Start the whole platform inside one container (Hugging Face Spaces).
#
# Order matches docker-compose.prod.yml: databases -> migrations + seed -> API -> PHP-FPM ->
# WordPress provisioning -> Caddy (public port 7860). The free Space has no persistent disk, so
# every start is a clean install: databases are created empty and re-seeded with the starter
# clinics, treatments, pages and products. Internal passwords are generated fresh each start.
#
# Required (Space secrets): WP_ADMIN_PASSWORD, SEED_ADMIN_PASSWORD
# Optional: WP_ADMIN_USER, WP_ADMIN_EMAIL, SEED_ADMIN_EMAIL, SEED_ADMIN_NAME, SITE_NAME, SITE_PUBLIC
set -euo pipefail

log() { echo "[start] $*"; }
gen() { python3 -c 'import secrets; print(secrets.token_urlsafe(36))'; }
hex40() { python3 -c 'import secrets; print(secrets.token_hex(20))'; }

: "${WP_ADMIN_PASSWORD:?Set WP_ADMIN_PASSWORD in the Space secrets}"
: "${SEED_ADMIN_PASSWORD:?Set SEED_ADMIN_PASSWORD in the Space secrets}"

RUN=/home/user/run
rm -rf "$RUN" && mkdir -p "$RUN"

# ---------- environment shared by every service ----------
if [ -n "${SPACE_HOST:-}" ]; then
  export WP_URL="https://${SPACE_HOST}" FORCE_HTTPS=1
else
  export WP_URL="${WP_URL:-http://localhost:7860}" FORCE_HTTPS="${FORCE_HTTPS:-0}"
fi
export SITE_NAME="${SITE_NAME:-Synergy Dental}" SITE_PUBLIC="${SITE_PUBLIC:-0}"
export WP_ADMIN_USER="${WP_ADMIN_USER:-sd_site_owner}" WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-owner@example.test}"
export SEED_ADMIN_EMAIL="${SEED_ADMIN_EMAIL:-admin@example.test}" SEED_ADMIN_NAME="${SEED_ADMIN_NAME:-Administrator}"

POSTGRES_PASSWORD="$(gen)"
export WP_DB_NAME=wordpress WP_DB_USER=wordpress WP_DB_PASSWORD="$(gen)"
export API_ENV=production JWT_SECRET="$(gen)" SERVICE_API_KEY="$(gen)" CORS_ALLOWED_ORIGINS=""
export DATABASE_URL="postgresql+psycopg://synergy:${POSTGRES_PASSWORD}@127.0.0.1:5432/synergy"
export SYNERGY_API_URL="http://127.0.0.1:8000/api/v1"
export WC_BASE_URL="http://127.0.0.1:7860" WC_CONSUMER_KEY="ck_$(hex40)" WC_CONSUMER_SECRET="cs_$(hex40)"
for k in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
  export "WP_$k=$(gen)"
done

# ---------- PostgreSQL (business data) ----------
PGBIN=/usr/lib/postgresql/17/bin
log "Starting PostgreSQL..."
printf '%s\n' "$POSTGRES_PASSWORD" > "$RUN/pgpw"
"$PGBIN/initdb" -D "$RUN/pg" -U synergy --pwfile="$RUN/pgpw" --auth=scram-sha-256 -E UTF8 --locale=C.UTF-8 >/dev/null
rm -f "$RUN/pgpw"
"$PGBIN/postgres" -D "$RUN/pg" -c listen_addresses=127.0.0.1 -c unix_socket_directories="$RUN" \
  -c shared_buffers=128MB -c logging_collector=off &
until "$PGBIN/pg_isready" -q -h 127.0.0.1 -U synergy; do sleep 1; done
PGPASSWORD="$POSTGRES_PASSWORD" "$PGBIN/createdb" -h 127.0.0.1 -U synergy synergy

# ---------- MariaDB (WordPress) ----------
log "Starting MariaDB..."
mariadb-install-db --no-defaults --user="$(id -un)" --datadir="$RUN/mysql" --skip-test-db >/dev/null
cat > "$RUN/init.sql" <<SQL
CREATE DATABASE IF NOT EXISTS wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'wordpress'@'127.0.0.1' IDENTIFIED BY '${WP_DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'wordpress'@'localhost' IDENTIFIED BY '${WP_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON wordpress.* TO 'wordpress'@'127.0.0.1', 'wordpress'@'localhost';
SQL
mariadbd --no-defaults --datadir="$RUN/mysql" --socket="$RUN/mysql.sock" --pid-file="$RUN/mysql.pid" \
  --bind-address=127.0.0.1 --port=3306 --init-file="$RUN/init.sql" --innodb-buffer-pool-size=256M &
until mariadb-admin --socket="$RUN/mysql.sock" ping >/dev/null 2>&1; do sleep 1; done
rm -f "$RUN/init.sql"

# ---------- FastAPI ----------
log "Migrating and seeding the business database..."
cd /opt/api
/opt/venv/bin/alembic upgrade head
/opt/venv/bin/python -m app.seed
log "Starting the API..."
/opt/venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8000 --proxy-headers --forwarded-allow-ips '*' &
until curl -fs http://127.0.0.1:8000/api/v1/health >/dev/null; do sleep 1; done

# ---------- WordPress ----------
log "Starting PHP-FPM..."
php-fpm -F &
log "Provisioning WordPress and WooCommerce..."
sh /provision/provision.sh

# ---------- public entry point ----------
log "Starting Caddy on :7860 ($WP_URL)"
XDG_DATA_HOME="$RUN/caddy" XDG_CONFIG_HOME="$RUN/caddy" caddy run --config /etc/caddy/Caddyfile --adapter caddyfile &

log "Ready: $WP_URL  |  dashboard: $WP_URL/dashboard/  |  API docs: $WP_URL/api/v1/docs"
# If any service exits, stop the container so Hugging Face restarts it.
wait -n
log "A service stopped unexpectedly; exiting."
exit 1
