<?php
/**
 * Plugin Name:       Synergy Integration (demo)
 * Description:       Treatments and clinics content types, the enquiry form that forwards to the business API, and WooCommerce customisations. Fictional demonstration only; not affiliated with Synergy Dental Group.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Independent demo project
 * License:           GPL-2.0-or-later
 * Text Domain:       synergy-integration
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

define( 'SYNERGY_INTEGRATION_VERSION', '1.0.0' );
define( 'SYNERGY_INTEGRATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'SYNERGY_INTEGRATION_URL', plugin_dir_url( __FILE__ ) );

require_once SYNERGY_INTEGRATION_DIR . 'includes/class-api-client.php';
require_once SYNERGY_INTEGRATION_DIR . 'includes/post-types.php';
require_once SYNERGY_INTEGRATION_DIR . 'includes/enquiry-form.php';
require_once SYNERGY_INTEGRATION_DIR . 'includes/settings.php';
require_once SYNERGY_INTEGRATION_DIR . 'includes/seo.php';

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'WooCommerce' ) ) {
			require_once SYNERGY_INTEGRATION_DIR . 'includes/woocommerce.php';
		}
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SYNERGY_INTEGRATION_DIR . 'includes/cli.php';
}

register_activation_hook(
	__FILE__,
	static function () {
		synergy_register_post_types();
		flush_rewrite_rules();
	}
);
