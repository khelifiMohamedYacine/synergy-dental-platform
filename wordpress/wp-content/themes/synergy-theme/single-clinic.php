<?php
/**
 * Single clinic: marketing copy from WordPress, address/hours/treatments from the API.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$day_names = array(
	'mon' => __( 'Monday', 'synergy-theme' ),
	'tue' => __( 'Tuesday', 'synergy-theme' ),
	'wed' => __( 'Wednesday', 'synergy-theme' ),
	'thu' => __( 'Thursday', 'synergy-theme' ),
	'fri' => __( 'Friday', 'synergy-theme' ),
	'sat' => __( 'Saturday', 'synergy-theme' ),
	'sun' => __( 'Sunday', 'synergy-theme' ),
);

while ( have_posts() ) :
	the_post();
	$slug    = (string) get_post_meta( get_the_ID(), 'sd_api_slug', true );
	$map_url = (string) get_post_meta( get_the_ID(), 'sd_map_url', true );
	$ref     = class_exists( 'Synergy_Api_Client' ) ? Synergy_Api_Client::reference_data() : null;
	$api     = null;
	foreach ( $ref['clinics'] ?? array() as $clinic ) {
		if ( $clinic['slug'] === $slug ) {
			$api = $clinic;
		}
	}
	$offered    = $ref['offered'][ $slug ] ?? array();
	$treatments = array_filter( $ref['treatments'] ?? array(), static fn( $t ) => in_array( $t['slug'], $offered, true ) );
	?>
	<div class="container">
		<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'synergy-theme' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'synergy-theme' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'clinic' ) ); ?>"><?php esc_html_e( 'Clinics', 'synergy-theme' ); ?></a></li>
				<li aria-current="page"><?php the_title(); ?></li>
			</ol>
		</nav>
		<div class="with-aside">
			<article class="entry">
				<h1><?php the_title(); ?></h1>
				<?php synergy_theme_feature_image(); ?>
				<div class="entry-content"><?php the_content(); ?></div>
				<?php if ( $treatments ) : ?>
					<h2><?php esc_html_e( 'Treatments at this clinic', 'synergy-theme' ); ?></h2>
					<ul class="pill-list">
						<?php foreach ( $treatments as $t ) : ?>
							<li><a href="<?php echo esc_url( synergy_theme_book_url( array( 'clinic' => $slug, 'treatment' => $t['slug'] ) ) ); ?>"><?php echo esc_html( $t['name'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</article>
			<aside class="card sticky-card" aria-label="<?php esc_attr_e( 'Contact details', 'synergy-theme' ); ?>">
				<?php if ( $api ) : ?>
					<h2 class="h3"><?php esc_html_e( 'Visit us', 'synergy-theme' ); ?></h2>
					<address><?php echo esc_html( $api['address_line1'] ); ?><br><?php echo $api['address_line2'] ? esc_html( $api['address_line2'] ) . '<br>' : ''; ?><?php echo esc_html( $api['city'] . ' ' . $api['postcode'] ); ?></address>
					<p><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $api['phone'] ) ); ?>"><?php echo esc_html( $api['phone'] ); ?></a></p>
					<h3 class="h4"><?php esc_html_e( 'Opening hours', 'synergy-theme' ); ?></h3>
					<table class="hours">
						<?php foreach ( $day_names as $key => $name ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $name ); ?></th>
								<td>
									<?php
									$ranges = $api['opening_hours'][ $key ] ?? array();
									echo $ranges ? esc_html( implode( ', ', array_map( static fn( $r ) => $r[0] . '–' . $r[1], $ranges ) ) ) : esc_html__( 'Closed', 'synergy-theme' );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
				<?php if ( $map_url ) : ?>
					<p><a href="<?php echo esc_url( $map_url ); ?>" rel="noopener"><?php esc_html_e( 'Get directions', 'synergy-theme' ); ?></a></p>
				<?php endif; ?>
				<a class="btn btn-block" href="<?php echo esc_url( synergy_theme_book_url( array( 'clinic' => $slug ) ) ); ?>"><?php esc_html_e( 'Book at this clinic', 'synergy-theme' ); ?></a>
			</aside>
		</div>
	</div>
	<?php
endwhile;
get_footer();
