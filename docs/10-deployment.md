# 10 – Deployment

**Status:** Implemented (Phase 8). · **Last updated:** 2026-11-13

## 1. Target

One small virtual machine running the same Docker Compose stack as development (doc 03 §8).
The chosen host is **Oracle Cloud Always Free** (Ampere A1 ARM, 2 OCPU / 12 GB RAM, Ubuntu 24.04):
it is free, always on and has persistent disks, so WordPress, WooCommerce and both databases
run unchanged. Every image used has an ARM64 build.

Free container platforms (Render, Cloud Run, Koyeb) were considered and rejected for this
project: they do not run Docker Compose, their free tiers sleep or cap resources, and WordPress
needs a persistent MariaDB that their free tiers do not provide.

HTTPS uses a free `<ip>.sslip.io` hostname (wildcard DNS that resolves to the IP inside the
name), so no domain purchase is needed. Caddy obtains and renews the Let's Encrypt certificate.

## 2. Production differences from development

| Concern | Development (`docker-compose.yml`) | Production (`docker-compose.prod.yml`) |
|---|---|---|
| Entry point | Caddy on `:8080`, HTTP | Caddy on 80/443, automatic HTTPS, HTTP→HTTPS redirect, HTTP/3 |
| Dashboard | Vite dev server with hot reload | Static build baked into the Caddy image (`infra/caddy.Dockerfile`) |
| API | Source bind mount, `--reload`, dev dependencies, port 8000 on 127.0.0.1 | Image only, `API_ENV=production` (refuses weak secrets), not published |
| WordPress | Theme/plugin mounted read-write | Mounted read-only; `DISALLOW_FILE_MODS`, `FORCE_SSL_ADMIN` |
| Headers | API security headers | + HSTS, CSP on the dashboard, `Permissions-Policy`; XML-RPC blocked |
| Operations | — | `restart: unless-stopped`, log rotation (3 × 10 MB), nightly backups |

## 3. Procedure

```sh
# 1. On your machine: generate secrets for the server's hostname (keep this file private).
py infra/make_env.py 203-0-113-10.sslip.io > .env.production

# 2. On the server (once): prepare the VM.
git clone https://github.com/khelifiMohamedYacine/synergy-dental-platform.git /opt/synergy
sh /opt/synergy/infra/server-setup.sh          # Docker, firewall 80/443, updates, swap
# copy .env.production to /opt/synergy/.env  (scp), then log out/in once

# 3. Deploy (and every later update).
cd /opt/synergy && sh infra/deploy.sh
```

`deploy.sh` pulls the code, builds images on the server (natively for ARM), starts the
databases, runs migrations and the seed as a one-off step **before** the API starts, starts
WordPress and Caddy, runs the idempotent WordPress provisioning, and installs the backup job.

The Oracle VCN security list must allow inbound TCP 80 and 443 in addition to the host
firewall rules that `server-setup.sh` adds.

## 4. Verification

The same Playwright suite used in development runs against any deployment:

```sh
cd e2e && BASE_URL=https://<host> E2E_ADMIN_PASSWORD=… E2E_STAFF_PASSWORD=… npx playwright test
```

Before the first real deployment, the production stack was run locally with `SITE_DOMAIN=localhost`
(Caddy's internal CA): all 6 journeys passed against the production build (static dashboard
under the CSP, HTTPS cookies, WordPress provisioning from an empty database).

## 5. Backups and recovery

`infra/backup.sh` runs nightly at 03:15 UTC: `pg_dump` (custom format) and `mariadb-dump`
(gzipped) into `/opt/synergy/backups`, owner-only permissions, 7-day retention. Restore:

```sh
docker compose -f docker-compose.prod.yml exec -T postgres pg_restore -U synergy -d synergy --clean < postgres-<stamp>.dump
gunzip -c wordpress-<stamp>.sql.gz | docker compose -f docker-compose.prod.yml exec -T mariadb sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" wordpress'
```

Off-site copies (object storage) are the next step for a real deployment; for a demo with
fictional data, local copies are sufficient.

## 6. Production path for a real business (not built)

Managed PostgreSQL/MySQL (automated backups, point-in-time recovery), container hosting with
at least two API replicas and Redis-backed rate limiting, a CDN in front of WordPress, secrets
in a secrets manager rather than a `.env` file, and centralised logs and uptime alerts.
