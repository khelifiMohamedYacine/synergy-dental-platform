<?php
/**
 * WP-CLI commands: `wp synergy sync-check` (ADR-04) and `wp synergy flush-cache`.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Synergy integration commands.
 */
class Synergy_CLI {

	/**
	 * Report slugs that exist on only one side (WordPress posts vs. the business API).
	 *
	 * ## EXAMPLES
	 *
	 *     wp synergy sync-check
	 *
	 * @subcommand sync-check
	 */
	public function sync_check(): void {
		delete_transient( Synergy_Api_Client::CACHE_KEY );
		$ref = Synergy_Api_Client::reference_data();
		if ( null === $ref ) {
			WP_CLI::error( 'The business API is not reachable at ' . Synergy_Api_Client::base_url() );
		}
		$problems = 0;
		foreach ( array(
			'treatment' => array_column( $ref['treatments'], 'slug' ),
			'clinic'    => array_column( $ref['clinics'], 'slug' ),
		) as $post_type => $api_slugs ) {
			$wp_slugs = array();
			foreach ( get_posts(
				array(
					'post_type'   => $post_type,
					'numberposts' => -1,
					'post_status' => 'publish',
				)
			) as $post ) {
				$wp_slugs[] = (string) get_post_meta( $post->ID, 'sd_api_slug', true );
			}
			foreach ( array_diff( $api_slugs, $wp_slugs ) as $slug ) {
				WP_CLI::warning( "{$post_type} '{$slug}' exists in the API but has no WordPress page." );
				++$problems;
			}
			foreach ( array_diff( $wp_slugs, $api_slugs ) as $slug ) {
				WP_CLI::warning( "{$post_type} '{$slug}' has a WordPress page but is not active in the API." );
				++$problems;
			}
		}
		if ( $problems ) {
			WP_CLI::error( "{$problems} slug mismatch(es) found.", false );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( 'WordPress and the API are in sync.' );
	}

	/**
	 * Clear the cached clinics/treatments so the next page view re-reads the API.
	 *
	 * @subcommand flush-cache
	 */
	public function flush_cache(): void {
		delete_transient( Synergy_Api_Client::CACHE_KEY );
		WP_CLI::success( 'Reference data cache cleared.' );
	}

	/**
	 * Delete test orders: those whose billing email uses the reserved example.test domain
	 * (automated tests and development data never use real addresses).
	 *
	 * @subcommand purge-test-orders
	 */
	public function purge_test_orders(): void {
		$matches = array();
		$page    = 1;
		do { // Collect first, delete afterwards, so deleting does not shift the pages.
			$orders = wc_get_orders(
				array(
					'limit'   => 100,
					'paged'   => $page++,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'type'    => 'shop_order',
				)
			);
			foreach ( $orders as $order ) {
				if ( str_ends_with( strtolower( (string) $order->get_billing_email() ), '@example.test' ) ) {
					$matches[] = $order;
				}
			}
		} while ( count( $orders ) === 100 );
		foreach ( $matches as $order ) {
			$order->delete( true );
		}
		WP_CLI::success( sprintf( 'Deleted %d test orders.', count( $matches ) ) );
	}
}

WP_CLI::add_command( 'synergy', 'Synergy_CLI' );
