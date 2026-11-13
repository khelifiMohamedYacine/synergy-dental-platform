<?php
/**
 * Treatments archive, grouped by category. Also used for the category taxonomy.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$current = is_tax( 'treatment_category' ) ? get_queried_object() : null;
$terms   = $current ? array( $current ) : get_terms(
	array(
		'taxonomy'   => 'treatment_category',
		'hide_empty' => true,
		'orderby'    => 'term_order',
	)
);
?>
<div class="container">
	<header class="page-header">
		<p class="eyebrow"><?php esc_html_e( 'Treatments', 'synergy-theme' ); ?></p>
		<h1><?php echo $current ? esc_html( $current->name ) : esc_html__( 'Our treatments', 'synergy-theme' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Every treatment starts with a conversation. Prices shown are starting prices; your dentist confirms the cost in writing before anything begins.', 'synergy-theme' ); ?></p>
	</header>
	<?php foreach ( $terms as $term ) : ?>
		<?php
		$posts = get_posts(
			array(
				'post_type'   => 'treatment',
				'numberposts' => -1,
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
				'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'treatment_category',
						'terms'    => $term->term_id,
					),
				),
			)
		);
		if ( ! $posts ) {
			continue;
		}
		?>
		<section class="section-compact" aria-labelledby="cat-<?php echo esc_attr( $term->slug ); ?>">
			<h2 id="cat-<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></h2>
			<div class="card-grid">
				<?php foreach ( $posts as $post ) { synergy_theme_treatment_card( $post ); } ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
<?php
get_footer();
