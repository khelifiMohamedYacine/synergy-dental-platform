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
}

WP_CLI::add_command( 'synergy', 'Synergy_CLI' );
