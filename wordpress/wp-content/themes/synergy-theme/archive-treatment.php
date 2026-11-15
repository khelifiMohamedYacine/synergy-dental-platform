<?php
/**
 * Treatments archive, grouped by category. Also used for the category taxonomy.
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$current = is_tax( 'treatment_category' ) ? get_queried_object() : null;
$all     = synergy_theme_categories();
$terms   = $current ? array( $current ) : $all;
?>
<header class="page-hero">
	<div class="container">
		<?php if ( $current ) : ?>
			<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'synergy-theme' ); ?>">
				<ol>
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'synergy-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"><?php esc_html_e( 'Treatments', 'synergy-theme' ); ?></a></li>
					<li aria-current="page"><?php echo esc_html( $current->name ); ?></li>
				</ol>
			</nav>
		<?php endif; ?>
		<p class="eyebrow"><?php esc_html_e( 'Treatments & prices', 'synergy-theme' ); ?></p>
		<h1><?php echo $current ? esc_html( $current->name ) : esc_html__( 'Our treatments', 'synergy-theme' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Every treatment starts with a conversation. Prices shown are starting prices; your dentist confirms the cost in writing before anything begins.', 'synergy-theme' ); ?></p>
		<?php if ( count( $all ) > 1 ) : ?>
			<nav class="chip-nav" aria-label="<?php esc_attr_e( 'Treatment categories', 'synergy-theme' ); ?>">
				<ul>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'treatment' ) ); ?>"<?php echo $current ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'All', 'synergy-theme' ); ?></a></li>
					<?php foreach ( $all as $term ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"<?php echo $current && $current->term_id === $term->term_id ? ' aria-current="page"' : ''; ?>><?php echo synergy_theme_icon( synergy_theme_category_icon( $term->slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
</header>
<div class="container treatments-list">
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
		$from = synergy_theme_category_from_price( $term );
		?>
		<section class="category-block" aria-labelledby="cat-<?php echo esc_attr( $term->slug ); ?>">
			<div class="category-head">
				<span class="service-icon"><?php echo synergy_theme_icon( synergy_theme_category_icon( $term->slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h2 id="cat-<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></h2>
					<p class="muted">
						<?php
						/* translators: %d: number of treatments */
						echo esc_html( sprintf( _n( '%d treatment', '%d treatments', count( $posts ), 'synergy-theme' ), count( $posts ) ) );
						if ( null !== $from ) {
							/* translators: %s: price */
							echo ' · ' . esc_html( sprintf( __( 'from %s', 'synergy-theme' ), synergy_theme_price( $from ) ) );
						}
						?>
					</p>
				</div>
			</div>
			<?php if ( $term->description ) : ?>
				<p class="category-desc"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>
			<div class="card-grid card-grid-4">
				<?php foreach ( $posts as $post ) { synergy_theme_treatment_card( $post ); } ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
<?php
get_footer();
