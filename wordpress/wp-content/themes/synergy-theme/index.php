<?php
/**
 * Fallback template (pages, posts, search).
 *
 * @package SynergyTheme
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="container container-narrow">
	<?php if ( have_posts() ) : ?>
		<?php if ( ! is_singular() ) : ?>
			<header class="page-header"><h1><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1></header>
		<?php endif; ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'entry' ); ?>>
				<?php if ( is_singular() ) : ?>
					<h1><?php the_title(); ?></h1>
					<div class="entry-content"><?php the_content(); ?></div>
				<?php else : ?>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Nothing found', 'synergy-theme' ); ?></h1>
	<?php endif; ?>
</div>
<?php
get_footer();
