<?php
/**
 * Theme setup. Presentation only: content types, the form and shop logic live in the
 * synergy-integration plugin, so switching theme loses no functionality.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;

define( 'SYNERGY_THEME_VERSION', '1.0.0' );

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
	$pounds = (int) $pence / 100;
	return '£' . number_format( $pounds, floor( $pounds ) === $pounds ? 0 : 2 );
}

/**
 * URL of the booking page, optionally pre-selecting a treatment or clinic.
 *
 * @param array $args Query args such as treatment or clinic slug.
 */
function synergy_theme_book_url( array $args = array() ): string {
	$page = get_page_by_path( 'book' );
	$url  = $page ? get_permalink( $page ) : home_url( '/book/' );
	return add_query_arg( $args, $url );
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
		<div class="card-media" aria-hidden="true"><span><?php echo esc_html( mb_substr( $post->post_title, 0, 1 ) ); ?></span></div>
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
		home_url( '/contact/' )    => __( 'Contact', 'synergy-theme' ),
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
