<?php
/**
 * Single treatment: header with key facts and photo, details and FAQs, booking card,
 * related treatments and aftercare products.
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
	$term     = $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
	$ref      = class_exists( 'Synergy_Api_Client' ) ? Synergy_Api_Client::reference_data() : null;
	$clinics  = 0;
	foreach ( $ref['offered'] ?? array() as $slugs ) {
		$clinics += in_array( $slug, $slugs, true ) ? 1 : 0;
	}
	$phone    = synergy_theme_main_phone();
	$products = function_exists( 'synergy_products_for_treatment' ) ? synergy_products_for_treatment( $slug ) : array();
	$related  = $term ? get_posts(
		array(
			'post_type'    => 'treatment',
			'numberposts'  => 4,
			'post__not_in' => array( $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			'orderby'      => 'menu_order title',
			'order'        => 'ASC',
			'tax_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'treatment_category',
					'terms'    => $term->term_id,
				),
			),
		)
	) : array();
	?>
	<header class="page-hero treatment-hero">
		<div class="container treatment-hero-inner">
			<div>
				<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'synergy-theme' ); ?>">
					<ol>
						<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'synergy-theme' ); ?></a></li>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"><?php esc_html_e( 'Treatments', 'synergy-theme' ); ?></a></li>
						<?php if ( $term ) : ?>
							<li><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
						<?php endif; ?>
						<li aria-current="page"><?php the_title(); ?></li>
					</ol>
				</nav>
				<?php if ( $term ) : ?>
					<p class="hero-kicker"><?php echo synergy_theme_icon( synergy_theme_category_icon( $term->slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $term->name ); ?></p>
				<?php endif; ?>
				<h1><?php the_title(); ?></h1>
				<p class="lead"><?php echo esc_html( (string) get_post_meta( $post_id, 'sd_summary', true ) ); ?></p>
				<ul class="fact-chips">
					<?php if ( $price ) : ?>
						<li><?php echo synergy_theme_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php /* translators: %s: price */ printf( esc_html__( 'From %s', 'synergy-theme' ), '<strong>' . esc_html( $price ) . '</strong>' ); ?></li>
					<?php endif; ?>
					<?php if ( $duration ) : ?>
						<li><?php echo synergy_theme_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <strong><?php echo esc_html( $duration ); ?></strong></li>
					<?php endif; ?>
					<?php if ( $clinics ) : ?>
						<li><?php echo synergy_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php /* translators: %d: number of clinics */ printf( esc_html( _n( 'At %d clinic', 'At %d clinics', $clinics, 'synergy-theme' ) ), (int) $clinics ); ?></li>
					<?php endif; ?>
				</ul>
			</div>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="treatment-photo">
					<?php
					the_post_thumbnail(
						'large',
						array(
							'alt'           => '',
							'fetchpriority' => 'high',
							'loading'       => 'eager',
							'sizes'         => '(max-width: 899px) 100vw, 480px',
						)
					);
					?>
				</figure>
			<?php endif; ?>
		</div>
	</header>
	<div class="container">
		<div class="with-aside">
			<article class="entry">
				<h2><?php esc_html_e( 'About this treatment', 'synergy-theme' ); ?></h2>
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
			<aside class="card sticky-card booking-card" aria-label="<?php esc_attr_e( 'Book this treatment', 'synergy-theme' ); ?>">
				<?php if ( $price ) : ?>
					<p class="booking-price"><span><?php esc_html_e( 'From', 'synergy-theme' ); ?></span> <?php echo esc_html( $price ); ?></p>
				<?php endif; ?>
				<ul class="check-list">
					<li><?php esc_html_e( 'Consultation and full check first', 'synergy-theme' ); ?></li>
					<li><?php esc_html_e( 'Written plan with every price', 'synergy-theme' ); ?></li>
					<li><?php esc_html_e( 'No obligation to go ahead', 'synergy-theme' ); ?></li>
				</ul>
				<a class="btn btn-block" href="<?php echo esc_url( synergy_theme_book_url( array( 'treatment' => $slug ) ) ); ?>"><?php esc_html_e( 'Request an appointment', 'synergy-theme' ); ?></a>
				<?php if ( $phone ) : ?>
					<p class="booking-phone"><?php esc_html_e( 'Or call', 'synergy-theme' ); ?> <a href="<?php echo esc_url( synergy_theme_tel( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p>
				<?php endif; ?>
			</aside>
		</div>
		<?php if ( $products ) : ?>
			<section class="section-compact">
				<h2><?php esc_html_e( 'Recommended aftercare', 'synergy-theme' ); ?></h2>
				<div class="card-grid card-grid-4">
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
		<?php if ( $related ) : ?>
			<section class="section-compact related">
				<h2>
					<?php
					/* translators: %s: category name */
					echo esc_html( sprintf( __( 'More in %s', 'synergy-theme' ), $term->name ) );
					?>
				</h2>
				<div class="card-grid card-grid-4">
					<?php foreach ( $related as $item ) { synergy_theme_treatment_card( $item ); } ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
	<?php
endwhile;
get_footer();
