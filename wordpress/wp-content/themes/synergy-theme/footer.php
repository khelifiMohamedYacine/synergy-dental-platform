<?php
/**
 * Site footer.
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
?>
</main>
<footer class="site-footer">
	<div class="container footer-grid">
		<div>
			<p class="wordmark">Synergy Dental <span class="wordmark-tag"><?php esc_html_e( 'demo', 'synergy-theme' ); ?></span></p>
			<p><?php esc_html_e( 'Private and family dentistry, planned around you.', 'synergy-theme' ); ?></p>
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
	</div>
	<div class="container footer-legal">
		<p><strong><?php esc_html_e( 'Independent technical demonstration – not affiliated with, commissioned by, or endorsed by Synergy Dental Group.', 'synergy-theme' ); ?></strong>
		<?php esc_html_e( 'All clinics, people, products and data are fictional. No real payments are processed.', 'synergy-theme' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
