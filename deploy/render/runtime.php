<?php
/**
 * Start-up fixes for the WordPress database baked into the Render image (run with `wp eval-file`):
 * 1. replace the build-time placeholder URL with the live URL (menus, content, metadata),
 * 2. set the admin password from the environment,
 * 3. install the WooCommerce REST key the API uses (same logic as provision/content.php).
 */

global $wpdb;

$synergy_old = 'http://synergy.build.invalid';
$synergy_new = untrailingslashit( (string) getenv( 'WP_URL' ) );
if ( $synergy_new && $synergy_new !== $synergy_old ) {
	foreach ( array( array( $wpdb->posts, 'post_content' ), array( $wpdb->posts, 'guid' ), array( $wpdb->postmeta, 'meta_value' ), array( $wpdb->options, 'option_value' ) ) as list( $table, $col ) ) {
		// Plain string values only; serialized values are rewritten below with correct lengths.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$col} = REPLACE({$col}, %s, %s) WHERE {$col} LIKE %s AND {$col} NOT LIKE %s", $synergy_old, $synergy_new, '%' . $wpdb->esc_like( $synergy_old ) . '%', 'a:%' ) ); // phpcs:ignore
	}
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s", '%' . $wpdb->esc_like( $synergy_old ) . '%' ) ) as $row ) { // phpcs:ignore
		$value = get_option( $row->option_name );
		$fixed = map_deep( $value, static fn( $v ) => is_string( $v ) ? str_replace( $synergy_old, $synergy_new, $v ) : $v );
		update_option( $row->option_name, $fixed );
	}
	WP_CLI::log( "URLs set to {$synergy_new}" );
}

$synergy_admin = get_user_by( 'login', (string) getenv( 'WP_ADMIN_USER' ) );
if ( $synergy_admin && getenv( 'WP_ADMIN_PASSWORD' ) ) {
	wp_set_password( (string) getenv( 'WP_ADMIN_PASSWORD' ), $synergy_admin->ID );
	if ( getenv( 'WP_ADMIN_EMAIL' ) ) {
		wp_update_user( array( 'ID' => $synergy_admin->ID, 'user_email' => (string) getenv( 'WP_ADMIN_EMAIL' ) ) );
	}
}

$ck = (string) getenv( 'WC_CONSUMER_KEY' );
$cs = (string) getenv( 'WC_CONSUMER_SECRET' );
if ( $ck && $cs ) {
	$table = $wpdb->prefix . 'woocommerce_api_keys';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE description LIKE %s", 'Synergy business API%' ) ); // phpcs:ignore
	$wpdb->insert( // phpcs:ignore
		$table,
		array(
			'user_id'         => $synergy_admin ? $synergy_admin->ID : 1,
			'description'     => 'Synergy business API',
			'permissions'     => 'read_write',
			'consumer_key'    => wc_api_hash( $ck ),
			'consumer_secret' => $cs,
			'truncated_key'   => substr( $ck, -7 ),
		)
	);
}
WP_CLI::success( 'Runtime configuration applied.' );
