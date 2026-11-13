<?php
/**
 * Home page (ui-design §3.1).
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$treatments = get_posts(
	array(
		'post_type'   => 'treatment',
		'numberposts' => 4,
		'orderby'     => 'menu_order title',
		'order'       => 'ASC',
	)
);
$clinics    = get_posts(
	array(
		'post_type'   => 'clinic',
		'numberposts' => 3,
		'orderby'     => 'title',
		'order'       => 'ASC',
	)
);
$products   = function_exists( 'wc_get_products' ) ? wc_get_products(
	array(
		'status'   => 'publish',
		'limit'    => 3,
		'featured' => true,
	)
) : array();
?>
<section class="hero">
	<div class="container hero-inner">
		<div class="hero-text">
			<p class="eyebrow"><?php esc_html_e( 'Private & family dentistry', 'synergy-theme' ); ?></p>
			<h1><?php esc_html_e( 'Confident smiles, planned around you', 'synergy-theme' ); ?></h1>
			<p class="lead"><?php esc_html_e( 'Check-ups, cosmetic treatments and implants from friendly teams who explain every option and every price before you decide.', 'synergy-theme' ); ?></p>
			<p class="hero-actions">
				<a class="btn" href="<?php echo esc_url( synergy_theme_book_url() ); ?>"><?php esc_html_e( 'Book a consultation', 'synergy-theme' ); ?></a>
				<a class="btn btn-secondary" href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"><?php esc_html_e( 'Explore treatments', 'synergy-theme' ); ?> <span aria-hidden="true">→</span></a>
			</p>
			<ul class="trust-row">
				<li><?php esc_html_e( '4 fictional clinics', 'synergy-theme' ); ?></li>
				<li><?php esc_html_e( 'Clear prices up front', 'synergy-theme' ); ?></li>
				<li><?php esc_html_e( 'Evening appointments', 'synergy-theme' ); ?></li>
			</ul>
		</div>
		<div class="hero-art">
			<?php
			$hero = (int) get_option( 'synergy_hero_image' );
			if ( $hero ) {
				// The largest image on the page: load it first (LCP), never lazily.
				echo wp_get_attachment_image(
					$hero,
					'large',
					false,
					array(
						'class'         => 'hero-photo',
						'fetchpriority' => 'high',
						'loading'       => 'eager',
						'sizes'         => '(max-width: 799px) 100vw, 45vw',
					)
				);
			}
			?>
			<p class="hero-badge"><strong><?php esc_html_e( 'Same-week', 'synergy-theme' ); ?></strong> <?php esc_html_e( 'consultations', 'synergy-theme' ); ?></p>
		</div>
	</div>
</section>

<?php if ( $treatments ) : ?>
<section class="section">
	<div class="container">
		<div class="section-head">
			<h2><?php esc_html_e( 'Popular treatments', 'synergy-theme' ); ?></h2>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"><?php esc_html_e( 'All treatments', 'synergy-theme' ); ?> <span aria-hidden="true">→</span></a>
		</div>
		<div class="card-grid">
			<?php foreach ( $treatments as $treatment ) { synergy_theme_treatment_card( $treatment ); } ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section-tint">
	<div class="container">
		<h2><?php esc_html_e( 'How it works', 'synergy-theme' ); ?></h2>
		<ol class="steps">
			<li><strong><?php esc_html_e( 'Enquire', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'Send a request online in two minutes.', 'synergy-theme' ); ?></span></li>
			<li><strong><?php esc_html_e( 'We call you', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'Usually the same working day.', 'synergy-theme' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Consultation', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'Meet the dentist and ask anything.', 'synergy-theme' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Your plan', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'A written plan with every price.', 'synergy-theme' ); ?></span></li>
		</ol>
	</div>
</section>

<?php if ( $clinics ) : ?>
<section class="section">
	<div class="container">
		<div class="section-head">
			<h2><?php esc_html_e( 'Find a clinic near you', 'synergy-theme' ); ?></h2>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'clinic' ) ); ?>"><?php esc_html_e( 'All clinics', 'synergy-theme' ); ?> <span aria-hidden="true">→</span></a>
		</div>
		<div class="card-grid card-grid-3">
			<?php foreach ( $clinics as $clinic ) : ?>
				<article class="card">
					<?php synergy_theme_card_image( $clinic ); ?>
					<div class="card-body">
						<h3><a class="card-link" href="<?php echo esc_url( get_permalink( $clinic ) ); ?>"><?php echo esc_html( get_the_title( $clinic ) ); ?></a></h3>
						<p><?php echo esc_html( get_the_excerpt( $clinic ) ); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $products ) : ?>
<section class="section section-tint">
	<div class="container">
		<div class="section-head">
			<h2><?php esc_html_e( 'Aftercare essentials', 'synergy-theme' ); ?></h2>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Visit the shop', 'synergy-theme' ); ?> <span aria-hidden="true">→</span></a>
		</div>
		<div class="card-grid card-grid-3">
			<?php foreach ( $products as $product ) : ?>
				<article class="card">
					<div class="card-body">
						<h3><a class="card-link" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
						<p><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
						<p class="price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="cta-band">
	<div class="container">
		<h2><?php esc_html_e( 'Ready to talk?', 'synergy-theme' ); ?></h2>
		<p><?php esc_html_e( 'Tell us what you are looking for and we will call you back.', 'synergy-theme' ); ?></p>
		<a class="btn btn-inverse" href="<?php echo esc_url( synergy_theme_book_url() ); ?>"><?php esc_html_e( 'Book a consultation', 'synergy-theme' ); ?></a>
	</div>
</section>
<?php
get_footer();
