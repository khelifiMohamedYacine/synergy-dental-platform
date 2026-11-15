<?php
/**
 * Home page: hero with quick booking, services, emergency care, reasons, treatments, steps,
 * clinics and aftercare.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$ref        = class_exists( 'Synergy_Api_Client' ) ? Synergy_Api_Client::reference_data() : null;
$phone      = synergy_theme_main_phone();
$categories = synergy_theme_categories();
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
$api_clinic = array();
foreach ( $ref['clinics'] ?? array() as $c ) {
	$api_clinic[ $c['slug'] ] = $c;
}
$products  = function_exists( 'wc_get_products' ) ? wc_get_products(
	array(
		'status'   => 'publish',
		'limit'    => 3,
		'featured' => true,
	)
) : array();
$emergency = null;
foreach ( $categories as $term ) {
	if ( str_contains( $term->slug, 'emergency' ) ) {
		$emergency = $term;
	}
}
$count = synergy_theme_clinic_count();
?>
<section class="hero hero-v3">
	<div class="container hero-inner">
		<div class="hero-text">
			<p class="hero-kicker"><?php echo synergy_theme_icon( 'tooth' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Private & family dentistry', 'synergy-theme' ); ?></p>
			<h1><?php esc_html_e( 'Healthy, confident smiles for the whole family', 'synergy-theme' ); ?></h1>
			<p class="lead"><?php esc_html_e( 'Check-ups, cosmetic dentistry, orthodontics and implants from friendly teams who explain every option and every price before you decide.', 'synergy-theme' ); ?></p>
			<p class="hero-actions">
				<a class="btn btn-large" href="<?php echo esc_url( synergy_theme_book_url() ); ?>"><?php esc_html_e( 'Book an appointment', 'synergy-theme' ); ?></a>
				<?php if ( $phone ) : ?>
					<a class="btn btn-secondary btn-large" href="<?php echo esc_url( synergy_theme_tel( $phone ) ); ?>"><?php echo synergy_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $phone ); ?></a>
				<?php else : ?>
					<a class="btn btn-secondary btn-large" href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"><?php esc_html_e( 'Explore treatments', 'synergy-theme' ); ?></a>
				<?php endif; ?>
			</p>
			<ul class="trust-row">
				<li><?php esc_html_e( 'New patients welcome', 'synergy-theme' ); ?></li>
				<li><?php esc_html_e( 'Written prices up front', 'synergy-theme' ); ?></li>
				<li><?php esc_html_e( 'Evening & Saturday appointments', 'synergy-theme' ); ?></li>
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
			<p class="hero-badge hero-badge-a"><?php echo synergy_theme_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><strong><?php esc_html_e( 'Same-week', 'synergy-theme' ); ?></strong> <?php esc_html_e( 'consultations', 'synergy-theme' ); ?></span></p>
			<?php if ( $count ) : ?>
				<p class="hero-badge hero-badge-b"><?php echo synergy_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><strong><?php echo esc_html( (string) $count ); ?></strong> <?php echo esc_html( _n( 'clinic', 'clinics', $count, 'synergy-theme' ) ); ?></span></p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $ref['treatments'] ) && ! empty( $ref['clinics'] ) ) : ?>
	<div class="container">
		<form class="quick-book" action="<?php echo esc_url( synergy_theme_book_url() ); ?>" method="get" aria-labelledby="qb-h">
			<h2 id="qb-h" class="quick-book-title"><?php esc_html_e( 'Request an appointment', 'synergy-theme' ); ?></h2>
			<div class="quick-book-field">
				<label for="qb-treatment"><?php esc_html_e( 'Treatment', 'synergy-theme' ); ?></label>
				<select id="qb-treatment" name="sd_treatment">
					<option value=""><?php esc_html_e( 'Not sure yet', 'synergy-theme' ); ?></option>
					<?php foreach ( $ref['treatments'] as $t ) : ?>
						<option value="<?php echo esc_attr( $t['slug'] ); ?>"><?php echo esc_html( $t['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="quick-book-field">
				<label for="qb-clinic"><?php esc_html_e( 'Clinic', 'synergy-theme' ); ?></label>
				<select id="qb-clinic" name="sd_clinic">
					<option value=""><?php esc_html_e( 'Any clinic', 'synergy-theme' ); ?></option>
					<?php foreach ( $ref['clinics'] as $c ) : ?>
						<option value="<?php echo esc_attr( $c['slug'] ); ?>"><?php echo esc_html( $c['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<button class="btn" type="submit"><?php esc_html_e( 'Continue', 'synergy-theme' ); ?> <?php echo synergy_theme_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</form>
	</div>
	<?php endif; ?>
</section>

<?php if ( $categories ) : ?>
<section class="section" aria-labelledby="services-h">
	<div class="container">
		<div class="section-intro">
			<p class="eyebrow"><?php esc_html_e( 'Our services', 'synergy-theme' ); ?></p>
			<h2 id="services-h"><?php esc_html_e( 'Complete dental care under one roof', 'synergy-theme' ); ?></h2>
			<p class="lead"><?php esc_html_e( 'From routine check-ups to straighter, brighter smiles and replacing missing teeth.', 'synergy-theme' ); ?></p>
		</div>
		<ul class="services">
			<?php foreach ( $categories as $term ) : ?>
				<?php $from = synergy_theme_category_from_price( $term ); ?>
				<li class="service">
					<span class="service-icon"><?php echo synergy_theme_icon( synergy_theme_category_icon( $term->slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3><a class="card-link" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></h3>
					<p><?php /* translators: %d: number of treatments */ echo esc_html( sprintf( _n( '%d treatment', '%d treatments', $term->count, 'synergy-theme' ), $term->count ) ); ?><?php if ( null !== $from ) : ?> · <?php /* translators: %s: price */ printf( esc_html__( 'from %s', 'synergy-theme' ), esc_html( synergy_theme_price( $from ) ) ); ?><?php endif; ?></p>
					<span class="service-more" aria-hidden="true"><?php echo synergy_theme_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php if ( $emergency || $phone ) : ?>
<section class="emergency" aria-labelledby="emergency-h">
	<div class="container emergency-inner">
		<span class="emergency-icon"><?php echo synergy_theme_icon( 'emergency' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<h2 id="emergency-h"><?php esc_html_e( 'Toothache, swelling or a broken tooth?', 'synergy-theme' ); ?></h2>
			<p><?php esc_html_e( 'We keep same-day emergency appointments free for patients in pain, including new patients.', 'synergy-theme' ); ?></p>
		</div>
		<p class="emergency-actions">
			<?php if ( $phone ) : ?>
				<a class="btn btn-inverse" href="<?php echo esc_url( synergy_theme_tel( $phone ) ); ?>"><?php echo synergy_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php /* translators: %s: phone number */ printf( esc_html__( 'Call %s', 'synergy-theme' ), esc_html( $phone ) ); ?></a>
			<?php endif; ?>
			<?php if ( $emergency ) : ?>
				<a class="btn btn-outline-light" href="<?php echo esc_url( get_term_link( $emergency ) ); ?>"><?php esc_html_e( 'Emergency care', 'synergy-theme' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</section>
<?php endif; ?>

<section class="section features-band" aria-labelledby="why-h">
	<div class="container why-grid">
		<div class="why-intro">
			<p class="eyebrow"><?php esc_html_e( 'Why patients choose us', 'synergy-theme' ); ?></p>
			<h2 id="why-h"><?php esc_html_e( 'Dentistry that puts you in control', 'synergy-theme' ); ?></h2>
			<p class="lead"><?php esc_html_e( 'No surprises and no pressure: you see the options, the prices and the timings, then choose what is right for you.', 'synergy-theme' ); ?></p>
			<a class="btn btn-secondary" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About our practice', 'synergy-theme' ); ?></a>
		</div>
		<ul class="features">
			<li class="feature">
				<?php echo synergy_theme_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'Clear, written prices', 'synergy-theme' ); ?></h3>
				<p><?php esc_html_e( 'Every option and every cost is set out in a written plan before treatment begins.', 'synergy-theme' ); ?></p>
			</li>
			<li class="feature">
				<?php echo synergy_theme_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'Appointments that fit', 'synergy-theme' ); ?></h3>
				<p><?php esc_html_e( 'Early, evening and Saturday appointments, with same-day emergency slots.', 'synergy-theme' ); ?></p>
			</li>
			<li class="feature">
				<?php echo synergy_theme_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'Gentle, unhurried care', 'synergy-theme' ); ?></h3>
				<p><?php esc_html_e( 'Longer first visits and a calm approach for nervous patients.', 'synergy-theme' ); ?></p>
			</li>
			<li class="feature">
				<?php echo synergy_theme_icon( 'scan' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'Modern technology', 'synergy-theme' ); ?></h3>
				<p><?php esc_html_e( 'Digital X-rays and 3D scans for accurate, comfortable treatment.', 'synergy-theme' ); ?></p>
			</li>
		</ul>
	</div>
</section>

<?php if ( $treatments ) : ?>
<section class="section">
	<div class="container">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Treatments', 'synergy-theme' ); ?></p>
				<h2><?php esc_html_e( 'Popular treatments', 'synergy-theme' ); ?></h2>
			</div>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"><?php esc_html_e( 'All treatments and prices', 'synergy-theme' ); ?> <span aria-hidden="true">→</span></a>
		</div>
		<div class="card-grid card-grid-4">
			<?php foreach ( $treatments as $treatment ) { synergy_theme_treatment_card( $treatment ); } ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section-tint" aria-labelledby="steps-h">
	<div class="container">
		<div class="section-intro">
			<p class="eyebrow"><?php esc_html_e( 'Your first visit', 'synergy-theme' ); ?></p>
			<h2 id="steps-h"><?php esc_html_e( 'Four simple steps', 'synergy-theme' ); ?></h2>
		</div>
		<ol class="steps">
			<li><strong><?php esc_html_e( 'Request online', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'Tell us what you need in two minutes.', 'synergy-theme' ); ?></span></li>
			<li><strong><?php esc_html_e( 'We call you', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'Usually the same working day, to find a time that suits.', 'synergy-theme' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Consultation', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'Meet your dentist, have a full check and ask anything.', 'synergy-theme' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Your plan', 'synergy-theme' ); ?></strong><span><?php esc_html_e( 'A written treatment plan with every price before you decide.', 'synergy-theme' ); ?></span></li>
		</ol>
	</div>
</section>

<?php if ( $clinics ) : ?>
<section class="section">
	<div class="container">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Our clinics', 'synergy-theme' ); ?></p>
				<h2><?php esc_html_e( 'Find a clinic near you', 'synergy-theme' ); ?></h2>
			</div>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'clinic' ) ); ?>"><?php esc_html_e( 'All clinics', 'synergy-theme' ); ?> <span aria-hidden="true">→</span></a>
		</div>
		<div class="card-grid card-grid-3">
			<?php foreach ( $clinics as $clinic ) : ?>
				<?php $api = $api_clinic[ get_post_meta( $clinic->ID, 'sd_api_slug', true ) ] ?? null; ?>
				<article class="card clinic-card">
					<?php synergy_theme_card_image( $clinic ); ?>
					<div class="card-body">
						<h3><a class="card-link" href="<?php echo esc_url( get_permalink( $clinic ) ); ?>"><?php echo esc_html( get_the_title( $clinic ) ); ?></a></h3>
						<?php if ( $api ) : ?>
							<p class="meta-line"><?php echo synergy_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $api['address_line1'] . ', ' . $api['city'] . ' ' . $api['postcode'] ); ?></p>
							<p class="meta-line"><?php echo synergy_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $api['phone'] ); ?></p>
						<?php else : ?>
							<p><?php echo esc_html( get_the_excerpt( $clinic ) ); ?></p>
						<?php endif; ?>
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
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Shop', 'synergy-theme' ); ?></p>
				<h2><?php esc_html_e( 'Aftercare essentials', 'synergy-theme' ); ?></h2>
			</div>
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
	<div class="container cta-inner">
		<div>
			<h2><?php esc_html_e( 'Ready for a healthier smile?', 'synergy-theme' ); ?></h2>
			<p><?php esc_html_e( 'Tell us what you are looking for and we will call you back, usually the same working day.', 'synergy-theme' ); ?></p>
		</div>
		<p class="cta-actions">
			<a class="btn btn-inverse btn-large" href="<?php echo esc_url( synergy_theme_book_url() ); ?>"><?php esc_html_e( 'Book an appointment', 'synergy-theme' ); ?></a>
			<?php if ( $phone ) : ?>
				<a class="btn btn-outline-light btn-large" href="<?php echo esc_url( synergy_theme_tel( $phone ) ); ?>"><?php /* translators: %s: phone number */ printf( esc_html__( 'Call %s', 'synergy-theme' ), esc_html( $phone ) ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</section>
<?php
get_footer();
