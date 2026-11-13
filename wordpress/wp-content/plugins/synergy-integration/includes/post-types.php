<?php
/**
 * Treatment and clinic content types, taxonomy and meta (doc 04 §8).
 *
 * Marketing copy lives here; canonical records (prices, opening hours, which clinic offers what)
 * live in PostgreSQL and are linked by slug (ADR-04).
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register CPTs, taxonomy and meta.
 */
function synergy_register_post_types(): void {
	register_post_type(
		'treatment',
		array(
			'labels'       => array(
				'name'          => __( 'Treatments', 'synergy-integration' ),
				'singular_name' => __( 'Treatment', 'synergy-integration' ),
				'add_new_item'  => __( 'Add treatment', 'synergy-integration' ),
				'edit_item'     => __( 'Edit treatment', 'synergy-integration' ),
			),
			'public'       => true,
			'has_archive'  => 'treatments',
			'rewrite'      => array( 'slug' => 'treatments' ),
			'menu_icon'    => 'dashicons-heart',
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'show_in_rest' => true,
		)
	);
	register_post_type(
		'clinic',
		array(
			'labels'       => array(
				'name'          => __( 'Clinics', 'synergy-integration' ),
				'singular_name' => __( 'Clinic', 'synergy-integration' ),
				'add_new_item'  => __( 'Add clinic', 'synergy-integration' ),
				'edit_item'     => __( 'Edit clinic', 'synergy-integration' ),
			),
			'public'       => true,
			'has_archive'  => 'clinics',
			'rewrite'      => array( 'slug' => 'clinics' ),
			'menu_icon'    => 'dashicons-location',
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'show_in_rest' => true,
		)
	);
	register_taxonomy(
		'treatment_category',
		'treatment',
		array(
			'labels'            => array(
				'name'          => __( 'Treatment categories', 'synergy-integration' ),
				'singular_name' => __( 'Treatment category', 'synergy-integration' ),
			),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'treatment-category' ),
		)
	);

	$meta = array(
		'treatment' => array(
			'sd_api_slug'   => 'string',
			'sd_summary'    => 'string',
			'sd_duration'   => 'string',
			'sd_price_from' => 'integer',
			'sd_faqs'       => 'string',
		),
		'clinic'    => array(
			'sd_api_slug' => 'string',
			'sd_map_url'  => 'string',
		),
	);
	foreach ( $meta as $post_type => $fields ) {
		foreach ( $fields as $key => $type ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sd_faqs' === $key ? 'synergy_sanitize_faqs' : ( 'integer' === $type ? 'absint' : 'sanitize_text_field' ),
					'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
}
add_action( 'init', 'synergy_register_post_types' );

/**
 * FAQs are stored as a JSON array of {q, a}. Anything else is discarded.
 *
 * @param mixed $value Raw value.
 */
function synergy_sanitize_faqs( $value ): string {
	$items = json_decode( (string) $value, true );
	if ( ! is_array( $items ) ) {
		return '[]';
	}
	$clean = array();
	foreach ( $items as $item ) {
		if ( ! empty( $item['q'] ) && ! empty( $item['a'] ) ) {
			$clean[] = array(
				'q' => sanitize_text_field( $item['q'] ),
				'a' => sanitize_textarea_field( $item['a'] ),
			);
		}
	}
	return wp_json_encode( $clean );
}

/**
 * Decoded FAQs for a treatment.
 *
 * @param int $post_id Post ID.
 * @return array<int, array{q: string, a: string}>
 */
function synergy_get_faqs( int $post_id ): array {
	$faqs = json_decode( (string) get_post_meta( $post_id, 'sd_faqs', true ), true );
	return is_array( $faqs ) ? $faqs : array();
}

/**
 * Classic meta box so editors can fill the custom fields without a block plugin.
 */
function synergy_add_meta_boxes(): void {
	add_meta_box( 'synergy_treatment_meta', __( 'Treatment details', 'synergy-integration' ), 'synergy_render_treatment_box', 'treatment', 'normal' );
	add_meta_box( 'synergy_clinic_meta', __( 'Clinic details', 'synergy-integration' ), 'synergy_render_clinic_box', 'clinic', 'side' );
}
add_action( 'add_meta_boxes', 'synergy_add_meta_boxes' );

/**
 * Render a labelled text input for a meta field.
 *
 * @param WP_Post $post  Post.
 * @param string  $key   Meta key.
 * @param string  $label Label.
 * @param string  $hint  Help text.
 */
function synergy_meta_input( WP_Post $post, string $key, string $label, string $hint = '' ): void {
	printf(
		'<p><label for="%1$s"><strong>%2$s</strong></label><br><input class="widefat" id="%1$s" name="%1$s" value="%3$s">%4$s</p>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( (string) get_post_meta( $post->ID, $key, true ) ),
		$hint ? '<span class="description">' . esc_html( $hint ) . '</span>' : ''
	);
}

/**
 * Treatment meta box.
 *
 * @param WP_Post $post Post.
 */
function synergy_render_treatment_box( WP_Post $post ): void {
	wp_nonce_field( 'synergy_save_meta', 'synergy_meta_nonce' );
	synergy_meta_input( $post, 'sd_api_slug', __( 'API slug', 'synergy-integration' ), __( 'Must match the treatment slug in the business API (wp synergy sync-check).', 'synergy-integration' ) );
	synergy_meta_input( $post, 'sd_summary', __( 'Summary', 'synergy-integration' ) );
	synergy_meta_input( $post, 'sd_duration', __( 'Duration (e.g. "2 visits")', 'synergy-integration' ) );
	synergy_meta_input( $post, 'sd_price_from', __( 'Price from (pence)', 'synergy-integration' ) );
	printf(
		'<p><label for="sd_faqs"><strong>%s</strong></label><br><textarea class="widefat" rows="5" id="sd_faqs" name="sd_faqs">%s</textarea><span class="description">%s</span></p>',
		esc_html__( 'FAQs (JSON)', 'synergy-integration' ),
		esc_textarea( (string) get_post_meta( $post->ID, 'sd_faqs', true ) ),
		esc_html__( 'Array of {"q": "...", "a": "..."}', 'synergy-integration' )
	);
}

/**
 * Clinic meta box.
 *
 * @param WP_Post $post Post.
 */
function synergy_render_clinic_box( WP_Post $post ): void {
	wp_nonce_field( 'synergy_save_meta', 'synergy_meta_nonce' );
	synergy_meta_input( $post, 'sd_api_slug', __( 'API slug', 'synergy-integration' ) );
	synergy_meta_input( $post, 'sd_map_url', __( 'Map URL', 'synergy-integration' ) );
}

/**
 * Save meta box fields (nonce + capability checked; values pass through registered sanitizers).
 *
 * @param int $post_id Post ID.
 */
function synergy_save_meta( int $post_id ): void {
	if ( ! isset( $_POST['synergy_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['synergy_meta_nonce'] ) ), 'synergy_save_meta' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( 'sd_api_slug', 'sd_summary', 'sd_duration', 'sd_price_from', 'sd_faqs', 'sd_map_url' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			// Sanitised by the callback registered in register_post_meta().
			update_post_meta( $post_id, $key, wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
	}
}
add_action( 'save_post_treatment', 'synergy_save_meta' );
add_action( 'save_post_clinic', 'synergy_save_meta' );
