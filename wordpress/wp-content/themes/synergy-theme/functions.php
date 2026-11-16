<?php
/**
 * Theme setup. Presentation only: content types, the form and shop logic live in the
 * synergy-integration plugin, so switching theme loses no functionality.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;

define( 'SYNERGY_THEME_VERSION', '2.1.1' );

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		register_nav_menus(
			array(
				'primary' => __( 'Primary navigation', 'synergy-theme' ),
				'footer'  => __( 'Footer navigation', 'synergy-theme' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$uri = get_template_directory_uri();
		wp_enqueue_style( 'synergy-main', $uri . '/assets/css/main.css', array(), SYNERGY_THEME_VERSION );
		wp_enqueue_script( 'synergy-menu', $uri . '/assets/js/menu.js', array(), SYNERGY_THEME_VERSION, array( 'strategy' => 'defer' ) );
		// No block library CSS on the front end: the theme does not use blocks for layout.
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'global-styles' );
	}
);

// Preload the body font so text renders in the final face with minimal layout shift.
add_action(
	'wp_head',
	static function () {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_template_directory_uri() . '/assets/fonts/inter-latin-wght-normal.woff2' )
		);
	},
	2
);

// Remove emoji scripts (extra request, not needed).
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/**
 * Format pence as £x.
 *
 * @param int|string $pence Price in pence.
 */
function synergy_theme_price( $pence ): string {
	if ( '' === $pence || null === $pence ) {
		return '';
	}
	$pence = (int) $pence;
	return '£' . number_format( $pence / 100, 0 === $pence % 100 ? 0 : 2 );
}

/**
 * URL of the booking page, optionally pre-selecting a treatment or clinic.
 *
 * The parameters are prefixed (sd_treatment / sd_clinic) because "treatment" and "clinic" are
 * the post types' own query variables: /book/?treatment=x would load the treatment page instead.
 *
 * @param array $args Keys 'treatment' and/or 'clinic' with API slugs.
 */
function synergy_theme_book_url( array $args = array() ): string {
	$page  = get_page_by_path( 'book' );
	$url   = $page ? get_permalink( $page ) : home_url( '/book/' );
	$query = array();
	foreach ( $args as $key => $value ) {
		$query[ 'sd_' . $key ] = $value;
	}
	return add_query_arg( $query, $url );
}

/**
 * Card photo (decorative: the card's heading already names it), lazy-loaded, 16:9 crop by CSS.
 * Falls back to a lettered tile when a post has no featured image.
 *
 * @param WP_Post $post Post.
 */
function synergy_theme_card_image( WP_Post $post ): void {
	if ( has_post_thumbnail( $post ) ) {
		echo '<div class="card-media">' . get_the_post_thumbnail(
			$post,
			'medium_large',
			array(
				'alt'     => '',
				'loading' => 'lazy',
				'sizes'   => '(max-width: 639px) 100vw, (max-width: 1199px) 50vw, 400px',
			)
		) . '</div>';
		return;
	}
	$terms = 'treatment' === $post->post_type ? get_the_terms( $post, 'treatment_category' ) : false;
	$icon  = $terms && ! is_wp_error( $terms ) ? synergy_theme_category_icon( $terms[0]->slug ) : ( 'clinic' === $post->post_type ? 'pin' : 'tooth' );
	echo '<div class="card-media card-media-fallback" aria-hidden="true">' . synergy_theme_icon( $icon ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup.
}

/**
 * Large photo at the top of a single treatment/clinic page.
 */
function synergy_theme_feature_image(): void {
	if ( has_post_thumbnail() ) {
		echo '<figure class="feature-image">';
		the_post_thumbnail(
			'large',
			array(
				'fetchpriority' => 'high',
				'loading'       => 'eager',
				'sizes'         => '(max-width: 899px) 100vw, 760px',
			)
		);
		echo '</figure>';
	}
}

/**
 * Render a treatment card.
 *
 * @param WP_Post $post Treatment post.
 */
function synergy_theme_treatment_card( WP_Post $post ): void {
	$terms = get_the_terms( $post, 'treatment_category' );
	$price = synergy_theme_price( get_post_meta( $post->ID, 'sd_price_from', true ) );
	?>
	<article class="card treatment-card">
		<?php synergy_theme_card_image( $post ); ?>
		<div class="card-body">
			<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
				<p class="eyebrow"><?php echo esc_html( $terms[0]->name ); ?></p>
			<?php endif; ?>
			<h3><a class="card-link" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
			<p><?php echo esc_html( (string) get_post_meta( $post->ID, 'sd_summary', true ) ); ?></p>
			<?php if ( $price ) : ?>
				<p class="price"><?php /* translators: %s: price */ printf( esc_html__( 'From %s', 'synergy-theme' ), esc_html( $price ) ); ?></p>
			<?php endif; ?>
		</div>
	</article>
	<?php
}

/**
 * Fallback menu when none is assigned.
 */
function synergy_theme_fallback_menu(): void {
	$items = array(
		home_url( '/treatments/' ) => __( 'Treatments', 'synergy-theme' ),
		home_url( '/clinics/' )    => __( 'Clinics', 'synergy-theme' ),
		home_url( '/about/' )      => __( 'About', 'synergy-theme' ),
		home_url( '/shop/' )       => __( 'Shop', 'synergy-theme' ),
	);
	echo '<ul class="menu">';
	foreach ( $items as $url => $label ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

// Woo: use the theme's wrapper instead of Woo's default one.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/**
 * Header cart link with item count. Without it, shoppers had no way to reach the cart or
 * checkout after adding a product.
 */
function synergy_theme_cart_link(): void {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$count = WC()->cart->get_cart_contents_count();
	printf(
		'<a class="header-cart" href="%1$s"><svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.5" fill="currentColor"/><circle cx="17" cy="20" r="1.5" fill="currentColor"/></svg><span class="header-cart-label">%2$s</span><span class="header-cart-count" data-count="%3$d">%4$s</span></a>',
		esc_url( wc_get_cart_url() ),
		esc_html__( 'Cart', 'synergy-theme' ),
		(int) $count,
		/* translators: %d: number of items in the cart */
		'<span class="screen-reader-text">' . esc_html( sprintf( _n( '%d item', '%d items', $count, 'synergy-theme' ), $count ) ) . '</span><span aria-hidden="true">' . (int) $count . '</span>'
	);
}

// Refresh the header cart after AJAX "Add to cart" (WooCommerce cart fragments).
add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( array $fragments ): array {
		ob_start();
		synergy_theme_cart_link();
		$fragments['a.header-cart'] = ob_get_clean();
		return $fragments;
	}
);

// Cart fragments are not loaded by default in recent WooCommerce versions.
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( function_exists( 'WC' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}
	},
	20
);

/**
 * Brand mark (inline SVG, decorative: the site name is always next to it).
 */
function synergy_theme_logo_mark(): void {
	echo '<svg class="logo-mark" aria-hidden="true" viewBox="0 0 40 40" width="36" height="36"><rect width="40" height="40" rx="12" fill="currentColor"/><path d="M13.5 11c-3 0-5 2.4-5 5.6 0 3.4 1.6 5.7 2.5 9.2.7 2.7 1.3 4.2 2.6 4.2 1.6 0 1.8-2.6 2.4-5 .4-1.6 1.1-2.4 2-2.4h4c.9 0 1.6.8 2 2.4.6 2.4.8 5 2.4 5 1.3 0 1.9-1.5 2.6-4.2.9-3.5 2.5-5.8 2.5-9.2 0-3.2-2-5.6-5-5.6-2.2 0-3.3 1.2-6.5 1.2S15.7 11 13.5 11Z" fill="#fff"/></svg>';
}

/**
 * Main contact phone: the first active clinic from the business API (empty when unavailable).
 */
function synergy_theme_main_phone(): string {
	if ( ! class_exists( 'Synergy_Api_Client' ) ) {
		return '';
	}
	$ref = Synergy_Api_Client::reference_data();
	return (string) ( $ref['clinics'][0]['phone'] ?? '' );
}

/**
 * Number of published clinics (for headline facts that stay true as clinics change).
 */
function synergy_theme_clinic_count(): int {
	return (int) wp_count_posts( 'clinic' )->publish;
}

/**
 * Line icons (24×24, stroke = currentColor), decorative: always next to visible text.
 *
 * @param string $name Icon name.
 */
function synergy_theme_icon( string $name ): string {
	$paths = array(
		'tooth'     => '<path d="M8 3.5c-2.8 0-4.5 2.2-4.5 5 0 3 1.4 5 2.2 8.2.6 2.4 1.1 3.8 2.3 3.8 1.4 0 1.6-2.3 2.1-4.4.3-1.4 1-2.1 1.9-2.1s1.6.7 1.9 2.1c.5 2.1.7 4.4 2.1 4.4 1.2 0 1.7-1.4 2.3-3.8.8-3.2 2.2-5.2 2.2-8.2 0-2.8-1.7-5-4.5-5-1.9 0-2.9 1-4 1s-2.1-1-4-1Z"/>',
		'sparkle'   => '<path d="M8 4c-2.6 0-4 2-4 4.5 0 2.6 1.2 4.4 1.9 7.2.5 2 1 3.3 2 3.3 1.3 0 1.4-2 1.8-3.8.3-1.2.8-1.8 1.6-1.8"/><path d="M17 3v4M15 5h4M19 10v3M17.5 11.5h3M13 15l.7 1.8 1.8.7-1.8.7L13 20l-.7-1.8-1.8-.7 1.8-.7Z"/>',
		'aligner'   => '<path d="M3 10c1.5-3 5-5 9-5s7.5 2 9 5"/><path d="M3 10c0 4 4 7 9 7s9-3 9-7"/><path d="M7 9.5v4M12 8.5v5.5M17 9.5v4"/>',
		'implant'   => '<path d="M7 3h10c0 3-1.5 5-5 5S7 6 7 3Z"/><path d="M9 11h6M9.5 14h5M10 17h4M11 20h2"/><path d="M9 8.5 10 21M15 8.5 14 21"/>',
		'emergency' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v9M7.5 12h9"/>',
		'shield'    => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
		'heart'     => '<path d="M12 20s-7.5-4.6-7.5-10.4A4.3 4.3 0 0 1 12 6.9a4.3 4.3 0 0 1 7.5 2.7C19.5 15.4 12 20 12 20Z"/>',
		'scan'      => '<path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16"/><path d="M4 12h16"/>',
		'list'      => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="m3.5 6 1 1 2-2M3.5 12l1 1 2-2M3.5 18l1 1 2-2"/>',
		'phone'     => '<path d="M5 4h3.5l1.5 4-2 1.5a11 11 0 0 0 6.5 6.5l1.5-2 4 1.5V19a1.5 1.5 0 0 1-1.6 1.5C10.6 20 4 13.4 3.5 5.6A1.5 1.5 0 0 1 5 4Z"/>',
		'pin'       => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.5"/>',
		'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	);
	return '<svg class="icon icon-' . esc_attr( $name ) . '" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ( $paths[ $name ] ?? $paths['tooth'] ) . '</svg>';
}

/**
 * Icon for a treatment category, by slug keyword (unknown categories get a tooth).
 *
 * @param string $slug Category slug.
 */
function synergy_theme_category_icon( string $slug ): string {
	foreach ( array(
		'cosmetic'    => 'sparkle',
		'ortho'       => 'aligner',
		'restor'      => 'implant',
		'implant'     => 'implant',
		'emergency'   => 'emergency',
		'hygien'      => 'shield',
		'child'       => 'heart',
		'general'     => 'tooth',
	) as $needle => $icon ) {
		if ( str_contains( $slug, $needle ) ) {
			return $icon;
		}
	}
	return 'tooth';
}

/**
 * Treatment categories that have treatments, in menu order.
 *
 * @return WP_Term[]
 */
function synergy_theme_categories(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'treatment_category',
			'hide_empty' => true,
			'orderby'    => 'term_order',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Lowest starting price (in pence) among a category's treatments, or null.
 *
 * @param WP_Term $term Category.
 */
function synergy_theme_category_from_price( WP_Term $term ): ?int {
	$prices = array();
	foreach ( get_posts(
		array(
			'post_type'   => 'treatment',
			'numberposts' => -1,
			'fields'      => 'ids',
			'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'treatment_category',
					'terms'    => $term->term_id,
				),
			),
		)
	) as $id ) {
		$value = get_post_meta( $id, 'sd_price_from', true );
		if ( '' !== $value ) {
			$prices[] = (int) $value;
		}
	}
	return $prices ? min( $prices ) : null;
}

/**
 * Tel: link target for a display phone number.
 *
 * @param string $phone Phone number as shown.
 */
function synergy_theme_tel( string $phone ): string {
	return 'tel:' . preg_replace( '/[^\d+]/', '', $phone );
}
