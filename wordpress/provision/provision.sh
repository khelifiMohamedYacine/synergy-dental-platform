#!/bin/sh
# Provision WordPress + WooCommerce from nothing with WP-CLI (FR-WP, doc 03 risks table).
# Idempotent: safe to run on every `docker compose up`. Runs in the wordpress:cli container.
set -eu

WOO_VERSION="11.3.0"
export WP_CLI_CACHE_DIR=/tmp/wp-cli-cache   # www-data has no writable home directory
cd /var/www/html

echo "Waiting for WordPress files and the database..."
i=0
until [ -f wp-config.php ] && wp db check --quiet >/dev/null 2>&1; do
  i=$((i + 1))
  [ "$i" -gt 90 ] && { echo "WordPress did not become ready in time" >&2; exit 1; }
  sleep 2
done

if ! wp core is-installed 2>/dev/null; then
  echo "Installing WordPress core..."
  wp core install \
    --url="$WP_URL" \
    --title="Synergy Dental (demo)" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
  wp post delete 1 2 3 --force >/dev/null 2>&1 || true   # sample post, page, privacy draft
  wp plugin delete akismet hello >/dev/null 2>&1 || true
else
  # Keep the admin password in step with the environment (e.g. after rotating secrets).
  wp user update "$WP_ADMIN_USER" --user_pass="$WP_ADMIN_PASSWORD" --skip-email >/dev/null
fi

echo "Configuring site options..."
wp option update blogdescription "Private and family dentistry – a fictional demonstration site" >/dev/null
wp option update timezone_string "Europe/London" >/dev/null
wp option update date_format "j F Y" >/dev/null
wp option update blog_public 0 >/dev/null
wp option update default_comment_status closed >/dev/null
wp option update default_ping_status closed >/dev/null
wp rewrite structure '/%postname%/' >/dev/null

echo "Activating theme and plugins..."
wp theme activate synergy-theme >/dev/null
if ! wp plugin is-installed woocommerce; then
  # Downloaded with curl rather than `wp plugin install <slug>`: PHP's HTTP client in this image
  # drops the TLS connection on large downloads, while the curl binary is reliable.
  zip="/tmp/woocommerce.$WOO_VERSION.zip"
  curl -fsSL --retry 3 --retry-all-errors -o "$zip" \
    "https://downloads.wordpress.org/plugin/woocommerce.$WOO_VERSION.zip"
  wp plugin install "$zip" >/dev/null
  rm -f "$zip"
fi
wp plugin activate woocommerce >/dev/null
wp plugin activate synergy-integration >/dev/null
# Remove bundled default themes we do not use (smaller attack surface).
for t in $(wp theme list --status=inactive --field=name); do wp theme delete "$t" >/dev/null || true; done

# Option updates that WooCommerce may veto (e.g. onboarding flags) must not abort provisioning.
opt() { wp option update "$@" >/dev/null 2>&1 || echo "  note: option '$1' left unchanged"; }

echo "Configuring WooCommerce..."
opt woocommerce_default_country "GB"
opt woocommerce_currency "GBP"
opt woocommerce_price_num_decimals 2
opt woocommerce_calc_taxes "no"
opt woocommerce_coming_soon "no"
opt woocommerce_store_pages_only "no"
opt woocommerce_enable_guest_checkout "yes"
opt woocommerce_enable_checkout_login_reminder "no"
opt woocommerce_allow_tracking "no"
opt woocommerce_show_marketplace_suggestions "no"
opt woocommerce_analytics_enabled "no"
opt woocommerce_task_list_hidden "yes"
opt woocommerce_onboarding_profile '{"skipped":true}' --format=json
opt woocommerce_synergy_demo_settings '{"enabled":"yes"}' --format=json
for gw in bacs cheque cod; do
  opt "woocommerce_${gw}_settings" '{"enabled":"no"}' --format=json
done
wp wc tool run install_pages --user="$WP_ADMIN_USER" >/dev/null 2>&1 || true

echo "Creating content, products, menus and API keys..."
wp eval-file /provision/content.php

wp rewrite flush >/dev/null
wp synergy flush-cache >/dev/null
echo "Checking slugs against the business API..."
wp synergy sync-check || echo "WARNING: sync-check reported differences (is the API running?)"
echo "Provisioning complete: $WP_URL"
