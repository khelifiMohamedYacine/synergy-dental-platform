<?php
/**
 * Single treatment (ui-design §3.2).
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$post_id  = get_the_ID();
	$slug     = (string) get_post_meta( $post_id, 'sd_api_slug', true );
	$price    = synergy_theme_price( get_post_meta( $post_id, 'sd_price_from', true ) );
	$duration = (string) get_post_meta( $post_id, 'sd_duration', true );
	$faqs     = function_exists( 'synergy_get_faqs' ) ? synergy_get_faqs( $post_id ) : array();
	$terms    = get_the_terms( $post_id, 'treatment_category' );
	$ref      = class_exists( 'Synergy_Api_Client' ) ? Synergy_Api_Client::reference_data() : null;
	$clinics  = 0;
	foreach ( $ref['offered'] ?? array() as $slugs ) {
		$clinics += in_array( $slug, $slugs, true ) ? 1 : 0;
	}
	$products = function_exists( 'synergy_products_for_treatment' ) ? synergy_products_for_treatment( $slug ) : array();
	?>
	<div class="container">
		<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'synergy-theme' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'synergy-theme' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"><?php esc_html_e( 'Treatments', 'synergy-theme' ); ?></a></li>
				<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
					<li><a href="<?php echo esc_url( get_term_link( $terms[0] ) ); ?>"><?php echo esc_html( $terms[0]->name ); ?></a></li>
				<?php endif; ?>
				<li aria-current="page"><?php the_title(); ?></li>
			</ol>
		</nav>
		<div class="with-aside">
			<article class="entry">
				<h1><?php the_title(); ?></h1>
				<?php synergy_theme_feature_image(); ?>
				<p class="lead"><?php echo esc_html( (string) get_post_meta( $post_id, 'sd_summary', true ) ); ?></p>
				<div class="entry-content"><?php the_content(); ?></div>
				<?php if ( $faqs ) : ?>
					<h2><?php esc_html_e( 'Frequently asked questions', 'synergy-theme' ); ?></h2>
					<div class="faqs">
						<?php foreach ( $faqs as $faq ) : ?>
							<details>
								<summary><?php echo esc_html( $faq['q'] ); ?></summary>
								<p><?php echo esc_html( $faq['a'] ); ?></p>
							</details>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</article>
			<aside class="card sticky-card" aria-label="<?php esc_attr_e( 'Treatment summary', 'synergy-theme' ); ?>">
				<dl class="facts">
					<?php if ( $price ) : ?>
						<dt><?php esc_html_e( 'Price from', 'synergy-theme' ); ?></dt><dd><?php echo esc_html( $price ); ?></dd>
					<?php endif; ?>
					<?php if ( $duration ) : ?>
						<dt><?php esc_html_e( 'Duration', 'synergy-theme' ); ?></dt><dd><?php echo esc_html( $duration ); ?></dd>
					<?php endif; ?>
					<?php if ( $clinics ) : ?>
						<dt><?php esc_html_e( 'Available at', 'synergy-theme' ); ?></dt>
						<dd><?php /* translators: %d: number of clinics */ printf( esc_html( _n( '%d clinic', '%d clinics', $clinics, 'synergy-theme' ) ), (int) $clinics ); ?></dd>
					<?php endif; ?>
				</dl>
				<a class="btn btn-block" href="<?php echo esc_url( synergy_theme_book_url( array( 'treatment' => $slug ) ) ); ?>"><?php esc_html_e( 'Enquire about this', 'synergy-theme' ); ?></a>
			</aside>
		</div>
		<?php if ( $products ) : ?>
			<section class="section">
				<h2><?php esc_html_e( 'Recommended aftercare', 'synergy-theme' ); ?></h2>
				<div class="card-grid">
					<?php foreach ( $products as $product ) : ?>
						<article class="card">
							<div class="card-body">
								<h3><a class="card-link" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
								<p class="price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
	<?php
endwhile;
get_footer();
