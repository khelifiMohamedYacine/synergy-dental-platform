# Deploying to Hugging Face Spaces (free, no card)

A free Hugging Face **Docker Space** (2 vCPU, 16 GB RAM) hosts the whole platform in a single
container. It complements the Oracle Cloud VM route in `docs/10-deployment.md`; the application
code is the same, only the packaging differs.

| File | Purpose |
|---|---|
| `Dockerfile` | One image: Debian 13, PHP 8.3-FPM + WordPress/WooCommerce, PostgreSQL 17, MariaDB, Python 3.13 venv for the API, Caddy, the built dashboard |
| `start.sh` | Starts databases → migrations + seed → API → PHP-FPM → WordPress provisioning → Caddy on port 7860 |
| `Caddyfile` | Same routing as `infra/Caddyfile.prod`, but plain HTTP behind Hugging Face's HTTPS proxy |
| `wp-config.php`, `php-fpm.conf`, `php.ini` | WordPress and PHP settings read from the environment |
| `space-README.md` | Becomes the Space's README (Hugging Face reads `sdk: docker` and `app_port` from it) |
| `publish.ps1`, `publish.py` | Stage the committed code and upload it to the Space, setting its secrets |

## Publish or update

```powershell
setx HF_TOKEN "hf_..."                         # once: a Hugging Face write token
powershell -File deploy\huggingface\publish.ps1  # every update (publishes the last commit)
```

The first run creates the Space and `.env.huggingface` (git-ignored) with the two logins:

- WordPress admin (`/wp-admin/`): user `sd_site_owner`, password `WP_ADMIN_PASSWORD`
- Dashboard (`/dashboard/`): `admin@example.test`, password `SEED_ADMIN_PASSWORD`

## Behaviour to know

- **No persistent disk** on the free tier: every restart is a clean install (starter clinics,
  treatments, pages and products are provisioned again; enquiries and orders are lost).
- The Space **sleeps after 48 hours without visitors**; the next visit wakes it (about 1–2 minutes).
- Internal database passwords, JWT and service keys are generated fresh on every start.

## Test locally

```sh
docker build -f deploy/huggingface/Dockerfile -t synergy-hf .
docker run --rm -p 7860:7860 -e WP_ADMIN_PASSWORD=... -e SEED_ADMIN_PASSWORD=... synergy-hf
# then: cd e2e && BASE_URL=http://localhost:7860 E2E_ADMIN_PASSWORD=<seed password> npx playwright test
```
