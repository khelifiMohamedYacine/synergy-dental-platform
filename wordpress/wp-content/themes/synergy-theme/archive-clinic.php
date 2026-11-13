<?php
/**
 * Clinics archive. Addresses and phone numbers come from the business API (canonical).
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$ref     = class_exists( 'Synergy_Api_Client' ) ? Synergy_Api_Client::reference_data() : null;
$by_slug = array();
foreach ( $ref['clinics'] ?? array() as $clinic ) {
	$by_slug[ $clinic['slug'] ] = $clinic;
}
$posts = get_posts(
	array(
		'post_type'   => 'clinic',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	)
);
?>
<div class="container">
	<header class="page-header">
		<p class="eyebrow"><?php esc_html_e( 'Clinics', 'synergy-theme' ); ?></p>
		<h1><?php esc_html_e( 'Find a clinic', 'synergy-theme' ); ?></h1>
	</header>
	<div class="card-grid card-grid-2">
		<?php foreach ( $posts as $post ) : ?>
			<?php $api = $by_slug[ get_post_meta( $post->ID, 'sd_api_slug', true ) ] ?? null; ?>
			<article class="card">
				<?php synergy_theme_card_image( $post ); ?>
				<div class="card-body">
					<h2 class="h3"><a class="card-link" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h2>
					<?php if ( $api ) : ?>
						<address><?php echo esc_html( implode( ', ', array_filter( array( $api['address_line1'], $api['address_line2'], $api['city'], $api['postcode'] ) ) ) ); ?></address>
						<p><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $api['phone'] ) ); ?>"><?php echo esc_html( $api['phone'] ); ?></a></p>
					<?php endif; ?>
					<p><?php echo esc_html( get_the_excerpt( $post ) ); ?></p>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</div>
<?php
get_footer();
