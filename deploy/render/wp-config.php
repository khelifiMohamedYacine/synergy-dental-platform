<?php
/**
 * WordPress configuration for the Render deployment: SQLite database inside the container
 * (SQLite Database Integration plugin, drop-in at wp-content/db.php). Values come from the
 * environment set by deploy/render/start.sh.
 */

define( 'DB_ENGINE', 'sqlite' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', '.ht.sqlite' );
// Unused with SQLite, but WordPress expects them to exist.
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $synergy_key ) {
	define( $synergy_key, (string) ( getenv( 'WP_' . $synergy_key ) ?: getenv( 'WP_SALT' ) . $synergy_key ) );
}
unset( $synergy_key );

$table_prefix = 'wp_';

// Behind Render's HTTPS proxy (and Caddy): make is_ssl() correct.
if ( getenv( 'FORCE_HTTPS' ) === '1' || ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}

define( 'WP_HOME', getenv( 'WP_URL' ) );
define( 'WP_SITEURL', getenv( 'WP_URL' ) );
define( 'DISALLOW_FILE_EDIT', true );
if ( ! defined( 'WP_CLI' ) ) {
	define( 'DISALLOW_FILE_MODS', true );
}
if ( getenv( 'FORCE_HTTPS' ) === '1' ) {
	define( 'FORCE_SSL_ADMIN', true );
}
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG', false );
define( 'DISABLE_WP_CRON', true ); // no background cron on a 0.1 CPU instance

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
