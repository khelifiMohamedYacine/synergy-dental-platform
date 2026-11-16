<?php
/**
 * WordPress configuration for the single-container Hugging Face deployment.
 * Every value comes from the environment set by deploy/huggingface/start.sh.
 */

define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'wordpress' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'wordpress' );
define( 'DB_PASSWORD', (string) getenv( 'WP_DB_PASSWORD' ) );
define( 'DB_HOST', '127.0.0.1:3306' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $synergy_key ) {
	define( $synergy_key, (string) getenv( 'WP_' . $synergy_key ) );
}
unset( $synergy_key );

$table_prefix = 'wp_';

// Behind Hugging Face's HTTPS proxy (and Caddy): make is_ssl() correct.
if ( getenv( 'FORCE_HTTPS' ) === '1' || ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}

define( 'WP_HOME', getenv( 'WP_URL' ) );
define( 'WP_SITEURL', getenv( 'WP_URL' ) );
define( 'DISALLOW_FILE_EDIT', true );
// Plugins are baked into the image; WP-CLI provisioning still needs to manage them.
if ( ! defined( 'WP_CLI' ) ) {
	define( 'DISALLOW_FILE_MODS', true );
}
if ( getenv( 'FORCE_HTTPS' ) === '1' ) {
	define( 'FORCE_SSL_ADMIN', true );
}
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG', false );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
