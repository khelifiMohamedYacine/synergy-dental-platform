<?php
/**
 * Lightweight SEO: meta descriptions and FAQPage / Dentist structured data.
 * WordPress core already provides the XML sitemap (/wp-sitemap.xml) and canonical links.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	static function () {
		$description = '';
		if ( is_singular( array( 'treatment', 'clinic' ) ) ) {
			$description = (string) get_post_meta( get_the_ID(), 'sd_summary', true );
			if ( ! $description ) {
				$description = get_the_excerpt();
			}
		} elseif ( is_front_page() ) {
			$description = get_bloginfo( 'description' );
		}
		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_strip_all_tags( $description ) ) );
		}
		// Demo site: keep it out of search engines.
		echo '<meta name="robots" content="noindex, nofollow">' . "\n";

		if ( is_singular( 'treatment' ) ) {
			$faqs = synergy_get_faqs( get_the_ID() );
			if ( $faqs ) {
				$data = array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => array_map(
						static fn( $f ) => array(
							'@type'          => 'Question',
							'name'           => $f['q'],
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => $f['a'],
							),
						),
						$faqs
					),
				);
				echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
			}
		}
	},
	1
);

// Robots: do not index the demo.
add_filter( 'wp_robots', static fn( $robots ) => array_merge( $robots, array( 'noindex' => true, 'nofollow' => true ) ) );
