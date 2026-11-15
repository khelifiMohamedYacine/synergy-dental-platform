<?php
/**
 * Site footer: brand and contact, clinics, information links, booking prompt.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
$clinics = get_posts(
	array(
		'post_type'   => 'clinic',
		'numberposts' => 6,
		'orderby'     => 'title',
		'order'       => 'ASC',
	)
);
$phone   = synergy_theme_main_phone();
?>
</main>
<footer class="site-footer">
	<div class="container footer-grid">
		<div class="footer-brand">
			<p class="wordmark"><?php synergy_theme_logo_mark(); ?><span class="wordmark-text"><?php bloginfo( 'name' ); ?></span></p>
			<p><?php esc_html_e( 'Private and family dentistry, planned around you.', 'synergy-theme' ); ?></p>
			<?php if ( $phone ) : ?>
				<p class="footer-phone"><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p>
			<?php endif; ?>
		</div>
		<nav aria-label="<?php esc_attr_e( 'Clinics', 'synergy-theme' ); ?>">
			<h2 class="footer-heading"><?php esc_html_e( 'Clinics', 'synergy-theme' ); ?></h2>
			<ul>
				<?php foreach ( $clinics as $clinic ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $clinic ) ); ?>"><?php echo esc_html( get_the_title( $clinic ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<nav aria-label="<?php esc_attr_e( 'Footer', 'synergy-theme' ); ?>">
			<h2 class="footer-heading"><?php esc_html_e( 'Information', 'synergy-theme' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => 'synergy_theme_fallback_menu',
				)
			);
			?>
		</nav>
		<div>
			<h2 class="footer-heading"><?php esc_html_e( 'Appointments', 'synergy-theme' ); ?></h2>
			<p><?php esc_html_e( 'Request a consultation online and we will call you back, usually the same working day.', 'synergy-theme' ); ?></p>
			<a class="btn btn-inverse btn-small" href="<?php echo esc_url( synergy_theme_book_url() ); ?>"><?php esc_html_e( 'Book a consultation', 'synergy-theme' ); ?></a>
		</div>
	</div>
	<div class="container footer-legal">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'Prices shown are starting prices; your dentist confirms your treatment plan and costs in writing.', 'synergy-theme' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
