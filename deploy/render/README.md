# Deploying to Render (free, no card)

One free Render **Docker web service** (512 MB, 0.1 CPU) runs the website, shop, staff dashboard
and API on one `onrender.com` address. Business data lives in an external free PostgreSQL
(Neon), because Render's free PostgreSQL is deleted after 30 days.

| Part | Where it runs |
|---|---|
| Enquiries, staff, clinics, treatments (PostgreSQL) | Neon free tier – permanent |
| FastAPI API, React dashboard (static), Caddy | Render web service |
| WordPress + WooCommerce | Render web service, on SQLite (official SQLite Database Integration plugin), provisioned at image build time |

WordPress cannot run on PostgreSQL (WooCommerce supports only MySQL/MariaDB), and MariaDB does
not fit in 512 MB next to everything else, so WordPress uses SQLite inside the container. The
unchanged `wordpress/provision/provision.sh` builds its database during `docker build`; at start-up
`runtime.php` only sets the live URL, the admin password and the WooCommerce REST key.
`sqlite-compat.php` turns off WooCommerce's checkout stock hold, whose MySQL-only query SQLite
cannot run (stock is still reduced when an order is placed).

## Set up (once)

1. **Neon** (https://neon.tech, sign in with GitHub): create a project in *Europe West 2 (London)*,
   then copy the connection string (`postgresql://…?sslmode=require`).
2. **Render** (https://render.com, sign in with GitHub): **New → Blueprint**, pick this repository,
   paste the Neon string as `DATABASE_URL`, **Apply**. The first build takes about 10 minutes.
3. Logins: Render → service → **Environment** shows `WP_ADMIN_PASSWORD` (WordPress, user
   `sd_site_owner`) and `SEED_ADMIN_PASSWORD` (dashboard, `admin@example.test`).

Every push to `main` redeploys automatically.

## Free-tier behaviour

- The service **sleeps after 15 minutes without visitors**; the next visit wakes it in about 1–2 minutes.
- Enquiries, staff accounts and clinic/treatment data are in Neon and **persist**.
- WordPress has no persistent disk: shop orders, products added in the dashboard and WordPress
  edits made on the live site **reset to the built state when the service sleeps or redeploys**.

## Test locally (same limits as Render)

```sh
docker build -t synergy-render .
docker run --rm -p 10000:10000 --memory=512m -e DATABASE_URL=postgresql://... \
  -e JWT_SECRET=<32+ chars> -e SERVICE_API_KEY=<32+ chars> -e WP_SALT=x \
  -e WP_ADMIN_PASSWORD=... -e SEED_ADMIN_PASSWORD=... synergy-render
```

Verified: all 8 Playwright journeys pass against this image at 512 MB with an external PostgreSQL;
cold start at 0.1 CPU is about 80 seconds and memory use stays around 230 MB.
