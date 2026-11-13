<?php
/**
 * WooCommerce wrapper. Woo's own templates are styled, not overridden (ui-design §3.4).
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="container shop">
	<?php woocommerce_content(); ?>
</div>
<?php
get_footer();
