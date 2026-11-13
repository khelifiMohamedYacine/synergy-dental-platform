<?php
/**
 * Site header.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content"><?php esc_html_e( 'Skip to content', 'synergy-theme' ); ?></a>
<header class="site-header">
	<div class="container header-inner">
		<a class="wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			Synergy Dental <span class="wordmark-tag"><?php esc_html_e( 'demo', 'synergy-theme' ); ?></span>
		</a>
		<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
			<span class="menu-toggle-bars" aria-hidden="true"></span>
			<span class="menu-toggle-label"><?php esc_html_e( 'Menu', 'synergy-theme' ); ?></span>
		</button>
		<nav id="primary-nav" class="primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'synergy-theme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => 'synergy_theme_fallback_menu',
					'depth'          => 1,
				)
			);
			?>
		</nav>
		<?php synergy_theme_cart_link(); ?>
		<a class="btn btn-small header-cta" href="<?php echo esc_url( synergy_theme_book_url() ); ?>"><?php esc_html_e( 'Book', 'synergy-theme' ); ?></a>
	</div>
</header>
<main id="content" class="site-main" tabindex="-1">
