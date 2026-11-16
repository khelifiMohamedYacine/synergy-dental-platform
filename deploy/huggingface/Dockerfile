# Hugging Face Spaces (Docker SDK): the whole platform in ONE container.
#
# Free Spaces run a single container (no Docker Compose), as user 1000, with one public port
# (7860) behind Hugging Face's HTTPS proxy and no persistent disk. So this image bundles
# PostgreSQL, MariaDB, PHP-FPM + WordPress/WooCommerce, the FastAPI API, the built React
# dashboard and Caddy, and deploy/huggingface/start.sh starts them in order.
# The application code is copied unchanged from the repository.
#
# Build context: repository root.
#   docker build -f deploy/huggingface/Dockerfile -t synergy-hf .
#   docker run --rm -p 7860:7860 -e WP_ADMIN_PASSWORD=... -e SEED_ADMIN_PASSWORD=... synergy-hf

# ---------- React dashboard: static build (same as infra/caddy.Dockerfile) ----------
FROM node:24-alpine AS dashboard
WORKDIR /app
COPY dashboard/package.json dashboard/package-lock.json ./
RUN npm ci
COPY dashboard/ ./
RUN npm run build

FROM caddy:2 AS caddy
FROM wordpress:cli-php8.3 AS wpcli

# ---------- Runtime: Debian 13 with PHP 8.3-FPM and WordPress core ----------
FROM wordpress:php8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      python3 python3-venv postgresql-17 mariadb-server mariadb-client curl unzip less \
 && rm -rf /var/lib/apt/lists/*

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy
COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp

# FastAPI backend in its own virtual environment.
COPY backend/requirements.txt /opt/api/requirements.txt
RUN python3 -m venv /opt/venv && /opt/venv/bin/pip install -r /opt/api/requirements.txt
COPY backend/ /opt/api/

# WordPress core, our theme and plugin, and the pinned WooCommerce + Stripe plugins (downloaded
# at build time, so provisioning only activates them).
ARG WOO_VERSION=11.3.0
ARG STRIPE_VERSION=11.2.0
RUN cp -a /usr/src/wordpress/. /var/www/html/ \
 && rm -rf /var/www/html/wp-content/themes/twenty* /var/www/html/wp-content/plugins/akismet /var/www/html/wp-content/plugins/hello.php \
 && cd /var/www/html/wp-content/plugins \
 && curl -fsSL --retry 3 -o /tmp/woo.zip "https://downloads.wordpress.org/plugin/woocommerce.${WOO_VERSION}.zip" \
 && curl -fsSL --retry 3 -o /tmp/stripe.zip "https://downloads.wordpress.org/plugin/woocommerce-gateway-stripe.${STRIPE_VERSION}.zip" \
 && unzip -q /tmp/woo.zip && unzip -q /tmp/stripe.zip && rm /tmp/*.zip
COPY wordpress/wp-content/themes/synergy-theme /var/www/html/wp-content/themes/synergy-theme
COPY wordpress/wp-content/plugins/synergy-integration /var/www/html/wp-content/plugins/synergy-integration
COPY wordpress/provision /provision
COPY deploy/huggingface/wp-config.php /var/www/html/wp-config.php

# Dashboard build, web server and PHP settings, start script.
COPY --from=dashboard /app/dist /srv/dashboard
COPY deploy/huggingface/Caddyfile /etc/caddy/Caddyfile
COPY deploy/huggingface/php-fpm.conf /usr/local/etc/php-fpm.d/zz-synergy.conf
COPY deploy/huggingface/php.ini /usr/local/etc/php/conf.d/zz-synergy.ini
COPY deploy/huggingface/start.sh /usr/local/bin/start.sh

# Hugging Face runs the container as uid 1000; everything that is written at runtime belongs to it.
RUN useradd --create-home --uid 1000 user \
 && chmod 755 /usr/local/bin/start.sh \
 && chown -R user:user /var/www/html
USER user
ENV HOME=/home/user
WORKDIR /home/user

EXPOSE 7860
CMD ["/usr/local/bin/start.sh"]
