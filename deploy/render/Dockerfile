# Render (free web service): the platform in ONE container, sized for 512 MB / 0.1 CPU.
#
# - Business data: external PostgreSQL (DATABASE_URL, e.g. Neon free tier) so it survives the
#   free instance's spin-downs and restarts.
# - WordPress + WooCommerce: SQLite (official "SQLite Database Integration" plugin) instead of
#   MariaDB, fully provisioned at BUILD time, so a cold start only fixes URLs/keys and starts
#   PHP-FPM, the API and Caddy. Free instances have no persistent disk: shop orders and WordPress
#   edits made on the live site last until the next spin-down (enquiries and staff data persist).
# The application code is copied unchanged.
#
# Build context: repository root.

# ---------- React dashboard: static build ----------
FROM node:24-alpine AS dashboard
WORKDIR /app
COPY dashboard/package.json dashboard/package-lock.json ./
RUN npm ci
COPY dashboard/ ./
RUN npm run build

FROM caddy:2 AS caddy
FROM wordpress:cli-php8.3 AS wpcli

# ---------- Runtime: Debian 13, PHP 8.3-FPM, WordPress core, Python 3.13 ----------
FROM wordpress:php8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

RUN apt-get update \
 && apt-get install -y --no-install-recommends python3 python3-venv curl unzip less \
 && rm -rf /var/lib/apt/lists/*

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy
COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp

# FastAPI backend.
COPY backend/requirements.txt /opt/api/requirements.txt
RUN python3 -m venv /opt/venv && /opt/venv/bin/pip install -r /opt/api/requirements.txt
COPY backend/ /opt/api/

# WordPress core + pinned plugins (WooCommerce, Stripe gateway, SQLite database driver).
ARG WOO_VERSION=11.3.0
ARG STRIPE_VERSION=11.2.0
ARG SQLITE_VERSION=3.0.2
RUN cp -a /usr/src/wordpress/. /var/www/html/ \
 && rm -rf /var/www/html/wp-content/themes/twenty* /var/www/html/wp-content/plugins/akismet /var/www/html/wp-content/plugins/hello.php \
 && cd /var/www/html/wp-content/plugins \
 && for p in "woocommerce.${WOO_VERSION}" "woocommerce-gateway-stripe.${STRIPE_VERSION}" "sqlite-database-integration.${SQLITE_VERSION}"; do \
      curl -fsSL --retry 3 -o /tmp/p.zip "https://downloads.wordpress.org/plugin/${p}.zip" && unzip -q /tmp/p.zip && rm /tmp/p.zip; \
    done \
 && sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/var/www/html/wp-content/plugins/sqlite-database-integration#" \
        -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
        sqlite-database-integration/db.copy > /var/www/html/wp-content/db.php \
 && mkdir -p /var/www/html/wp-content/database
COPY wordpress/wp-content/themes/synergy-theme /var/www/html/wp-content/themes/synergy-theme
COPY wordpress/wp-content/plugins/synergy-integration /var/www/html/wp-content/plugins/synergy-integration
COPY wordpress/provision /provision
COPY deploy/render/wp-config.php /var/www/html/wp-config.php
COPY deploy/render/sqlite-compat.php /var/www/html/wp-content/mu-plugins/sqlite-compat.php
COPY deploy/render/runtime.php /opt/render/runtime.php
COPY deploy/render/wp-shim /opt/wpshim/wp

COPY --from=dashboard /app/dist /srv/dashboard
COPY deploy/render/Caddyfile /etc/caddy/Caddyfile
COPY deploy/render/php-fpm.conf /usr/local/etc/php-fpm.d/zz-synergy.conf
COPY deploy/huggingface/php.ini /usr/local/etc/php/conf.d/zz-synergy.ini
COPY deploy/render/start.sh /usr/local/bin/start.sh

RUN useradd --create-home --uid 1000 user \
 && chmod 755 /usr/local/bin/start.sh /opt/wpshim/wp \
 && chown -R user:user /var/www/html
USER user
ENV HOME=/home/user

# Provision WordPress + WooCommerce into the SQLite database now, with the unchanged
# provisioning script. Placeholder URL and admin password are replaced at start-up.
# (The shim answers `wp db check`, which needs the MySQL client, for SQLite.)
RUN cd /var/www/html \
 && PATH=/opt/wpshim:$PATH WP_URL=http://synergy.build.invalid WP_ADMIN_USER=sd_site_owner \
    WP_ADMIN_PASSWORD="$(head -c 24 /dev/urandom | base64)" WP_ADMIN_EMAIL=owner@example.test \
    SYNERGY_API_URL=http://127.0.0.1:8000/api/v1 SITE_NAME="Synergy Dental" SITE_PUBLIC=0 \
    sh /provision/provision.sh

WORKDIR /home/user
ENV PORT=10000
EXPOSE 10000
CMD ["/usr/local/bin/start.sh"]
