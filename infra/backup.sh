#!/bin/sh
# Nightly database backups (doc 03 §8). Keeps 7 days. Installed as a cron job by deploy.sh:
#   15 3 * * *  /opt/synergy/infra/backup.sh >> /opt/synergy/backups/backup.log 2>&1
# Dumps contain (fictional) personal data: the directory is readable by the owner only.
set -eu
cd "$(dirname "$0")/.."
DEST=/opt/synergy/backups
STAMP=$(date -u +%Y%m%dT%H%MZ)
mkdir -p "$DEST" && chmod 700 "$DEST"
C="docker compose -f docker-compose.prod.yml --env-file .env"

$C exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' > "$DEST/postgres-$STAMP.dump"
$C exec -T mariadb sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction "$MARIADB_DATABASE"' | gzip > "$DEST/wordpress-$STAMP.sql.gz"
chmod 600 "$DEST"/*-"$STAMP".*

find "$DEST" -name 'postgres-*.dump' -mtime +7 -delete
find "$DEST" -name 'wordpress-*.sql.gz' -mtime +7 -delete
echo "$(date -u +%FT%TZ) backup ok: $(ls -1 "$DEST" | wc -l) files"
