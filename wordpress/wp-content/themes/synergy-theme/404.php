<?php
/**
 * Not found.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="container container-narrow page-header">
	<h1><?php esc_html_e( 'Page not found', 'synergy-theme' ); ?></h1>
	<p class="lead"><?php esc_html_e( 'The page you were looking for has moved or does not exist.', 'synergy-theme' ); ?></p>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the home page', 'synergy-theme' ); ?></a></p>
</div>
<?php
get_footer();
