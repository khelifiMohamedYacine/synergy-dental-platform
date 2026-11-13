<?php
/**
 * About page: photo on the left, story on the right, then values, clinics and a call to action.
 * WordPress picks this template automatically for the page with the slug "about".
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$clinics = get_posts(
	array(
		'post_type'   => 'clinic',
		'numberposts' => 4,
		'orderby'     => 'title',
		'order'       => 'ASC',
	)
);

while ( have_posts() ) :
	the_post();
	?>
	<section class="about-intro">
		<div class="container about-grid">
			<figure class="about-photo">
				<?php
				if ( has_post_thumbnail() ) {
					the_post_thumbnail(
						'large',
						array(
							'fetchpriority' => 'high',
							'loading'       => 'eager',
							'sizes'         => '(max-width: 899px) 100vw, 50vw',
						)
					);
				}
				?>
				<figcaption><?php esc_html_e( 'Every treatment starts with a conversation with your dentist.', 'synergy-theme' ); ?></figcaption>
			</figure>
			<div class="about-text">
				<p class="eyebrow"><?php esc_html_e( 'About us', 'synergy-theme' ); ?></p>
				<h1><?php esc_html_e( 'Careful dentistry, explained clearly', 'synergy-theme' ); ?></h1>
				<div class="entry-content"><?php the_content(); ?></div>
				<dl class="stats">
					<div><dt><?php esc_html_e( 'Clinics', 'synergy-theme' ); ?></dt><dd>4</dd></div>
					<div><dt><?php esc_html_e( 'Treatments', 'synergy-theme' ); ?></dt><dd>12</dd></div>
					<div><dt><?php esc_html_e( 'Evening & Saturday', 'synergy-theme' ); ?></dt><dd><?php esc_html_e( 'Yes', 'synergy-theme' ); ?></dd></div>
				</dl>
				<p class="hero-actions">
					<a class="btn" href="<?php echo esc_url( synergy_theme_book_url() ); ?>"><?php esc_html_e( 'Book a consultation', 'synergy-theme' ); ?></a>
					<a class="btn btn-secondary" href="<?php echo esc_url( get_post_type_archive_link( 'clinic' ) ); ?>"><?php esc_html_e( 'Find a clinic', 'synergy-theme' ); ?></a>
				</p>
			</div>
		</div>
	</section>

	<section class="section section-tint">
		<div class="container">
			<h2><?php esc_html_e( 'What you can expect', 'synergy-theme' ); ?></h2>
			<div class="values">
				<div class="value">
					<h3><?php esc_html_e( 'Clear prices', 'synergy-theme' ); ?></h3>
					<p><?php esc_html_e( 'A written plan with every cost before treatment begins. No surprises on the day.', 'synergy-theme' ); ?></p>
				</div>
				<div class="value">
					<h3><?php esc_html_e( 'Time to talk', 'synergy-theme' ); ?></h3>
					<p><?php esc_html_e( 'Longer first appointments so you can ask questions and understand your options.', 'synergy-theme' ); ?></p>
				</div>
				<div class="value">
					<h3><?php esc_html_e( 'Gentle care', 'synergy-theme' ); ?></h3>
					<p><?php esc_html_e( 'Nervous patients are welcome. Tell us how you feel and we will go at your pace.', 'synergy-theme' ); ?></p>
				</div>
				<div class="value">
					<h3><?php esc_html_e( 'Modern equipment', 'synergy-theme' ); ?></h3>
					<p><?php esc_html_e( 'Digital X-rays and 3D scans mean fewer impressions and more accurate results.', 'synergy-theme' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<?php if ( $clinics ) : ?>
	<section class="section">
		<div class="container">
			<div class="section-head">
				<h2><?php esc_html_e( 'Our clinics', 'synergy-theme' ); ?></h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'clinic' ) ); ?>"><?php esc_html_e( 'All clinic details', 'synergy-theme' ); ?> <span aria-hidden="true">→</span></a>
			</div>
			<div class="card-grid">
				<?php foreach ( $clinics as $clinic ) : ?>
					<article class="card">
						<?php synergy_theme_card_image( $clinic ); ?>
						<div class="card-body">
							<h3><a class="card-link" href="<?php echo esc_url( get_permalink( $clinic ) ); ?>"><?php echo esc_html( get_the_title( $clinic ) ); ?></a></h3>
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
endwhile;
get_footer();
