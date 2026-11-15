<?php
/**
 * WooCommerce customisations (FR-SHOP): "Recommended after" product field, related products
 * on treatment pages, collection from a clinic, and the delivery information banner.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

/*
 * Payments are handled by WooCommerce's own gateways: Stripe (card payments, configured with the
 * clinic group's Stripe account), bank transfer, and pay on collection (see provision.sh).
 */

/*
 * "Recommended after" field (FR-SHOP-06): links a product to a treatment slug.
 */
add_action(
	'woocommerce_product_options_general_product_data',
	static function () {
		$options = array( '' => __( '— none —', 'synergy-integration' ) );
		foreach ( get_posts(
			array(
				'post_type'   => 'treatment',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		) as $treatment ) {
			$slug = (string) get_post_meta( $treatment->ID, 'sd_api_slug', true );
			if ( $slug ) {
				$options[ $slug ] = $treatment->post_title;
			}
		}
		woocommerce_wp_select(
			array(
				'id'          => '_sd_recommended_after',
				'label'       => __( 'Recommended after', 'synergy-integration' ),
				'options'     => $options,
				'desc_tip'    => true,
				'description' => __( 'Shown on the product page and on the treatment page.', 'synergy-integration' ),
			)
		);
	}
);

add_action(
	'woocommerce_admin_process_product_object',
	static function ( WC_Product $product ) {
		// Nonce verified by WooCommerce before this hook runs.
		$value = isset( $_POST['_sd_recommended_after'] ) ? sanitize_key( wp_unslash( $_POST['_sd_recommended_after'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( '_sd_recommended_after', $value );
	}
);

add_action(
	'woocommerce_single_product_summary',
	static function () {
		global $product;
		$slug = $product ? (string) $product->get_meta( '_sd_recommended_after' ) : '';
		if ( ! $slug ) {
			return;
		}
		$treatment = synergy_find_treatment_post( $slug );
		if ( $treatment ) {
			printf(
				'<p class="sd-recommended">%s <a href="%s">%s</a></p>',
				esc_html__( 'Recommended after:', 'synergy-integration' ),
				esc_url( get_permalink( $treatment ) ),
				esc_html( get_the_title( $treatment ) )
			);
		}
	},
	25
);

/**
 * Treatment post with a given API slug.
 *
 * @param string $slug API slug.
 */
function synergy_find_treatment_post( string $slug ): ?WP_Post {
	$posts = get_posts(
		array(
			'post_type'   => 'treatment',
			'numberposts' => 1,
			'meta_key'    => 'sd_api_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return $posts[0] ?? null;
}

/**
 * Products recommended after a treatment (used by the theme's single-treatment template).
 *
 * @param string $slug  Treatment API slug.
 * @param int    $limit Maximum number of products.
 * @return WC_Product[]
 */
function synergy_products_for_treatment( string $slug, int $limit = 4 ): array {
	if ( ! $slug ) {
		return array();
	}
	return wc_get_products(
		array(
			'status'     => 'publish',
			'limit'      => $limit,
			'meta_key'   => '_sd_recommended_after', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
}

/*
 * "Collect from one of our clinics": the shopper picks which clinic (required for that method).
 */

/**
 * Active clinics from the business API, as slug => "Name, City".
 *
 * @return array<string, string>
 */
function synergy_collection_clinics(): array {
	$ref     = Synergy_Api_Client::reference_data();
	$clinics = array();
	foreach ( $ref['clinics'] ?? array() as $clinic ) {
		$clinics[ $clinic['slug'] ] = $clinic['name'] . ', ' . $clinic['city'];
	}
	return $clinics;
}

/**
 * True when the chosen delivery method is clinic collection.
 */
function synergy_is_collection_chosen(): bool {
	$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
	foreach ( $chosen as $method ) {
		if ( str_starts_with( (string) $method, 'local_pickup' ) ) {
			return true;
		}
	}
	return false;
}

add_action(
	'woocommerce_review_order_after_shipping',
	static function () {
		$clinics = synergy_collection_clinics();
		if ( ! $clinics || ! synergy_is_collection_chosen() ) {
			return;
		}
		// Refreshed by WooCommerce's update_order_review AJAX whenever the delivery method changes.
		echo '<tr class="sd-collection"><th>' . esc_html__( 'Collect from', 'synergy-integration' ) . '</th><td>';
		woocommerce_form_field(
			'sd_collection_clinic',
			array(
				'type'     => 'select',
				'label'    => __( 'Choose a clinic', 'synergy-integration' ),
				'required' => true,
				'options'  => array( '' => __( 'Choose a clinic…', 'synergy-integration' ) ) + $clinics,
			),
			WC()->checkout()->get_value( 'sd_collection_clinic' )
		);
		echo '</td></tr>';
	}
);

add_action(
	'woocommerce_after_checkout_validation',
	static function ( array $data, WP_Error $errors ) {
		if ( ! synergy_is_collection_chosen() ) {
			return;
		}
		$clinic = isset( $_POST['sd_collection_clinic'] ) ? sanitize_key( wp_unslash( $_POST['sd_collection_clinic'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( synergy_collection_clinics()[ $clinic ] ) ) {
			$errors->add( 'sd_collection_clinic', __( 'Choose the clinic you will collect your order from.', 'synergy-integration' ) );
		}
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_create_order',
	static function ( WC_Order $order ) {
		$clinic  = isset( $_POST['sd_collection_clinic'] ) ? sanitize_key( wp_unslash( $_POST['sd_collection_clinic'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$clinics = synergy_collection_clinics();
		if ( synergy_is_collection_chosen() && isset( $clinics[ $clinic ] ) ) {
			$order->update_meta_data( '_sd_collection_clinic', $clinics[ $clinic ] );
		}
	}
);

/**
 * Collection clinic on the thank-you page, emails and the admin order screen.
 *
 * @param WC_Order $order Order.
 */
function synergy_order_extras( WC_Order $order ): void {
	$clinic = (string) $order->get_meta( '_sd_collection_clinic' );
	if ( $clinic ) {
		printf( '<p class="sd-order-extra"><strong>%s</strong> %s</p>', esc_html__( 'Collect from:', 'synergy-integration' ), esc_html( $clinic ) );
	}
}
add_action( 'woocommerce_order_details_after_order_table', 'synergy_order_extras' );
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'synergy_order_extras' );
add_action( 'woocommerce_email_after_order_table', 'synergy_order_extras' );

/*
 * Delivery information banner on the shop, product and cart pages (matches provision.sh).
 */
function synergy_offer_banner(): void {
	echo '<div class="sd-offer" role="note"><strong>' . esc_html__( 'Delivery:', 'synergy-integration' ) . '</strong> ' .
		esc_html__( 'free UK delivery on orders over £30, or collect free from any of our clinics.', 'synergy-integration' ) . '</div>';
}
add_action( 'woocommerce_before_shop_loop', 'synergy_offer_banner', 5 );
add_action( 'woocommerce_before_cart', 'synergy_offer_banner' );
add_action( 'woocommerce_before_single_product', 'synergy_offer_banner' );

// The shop is empty until products are added: say so politely instead of WooCommerce's default.
add_action(
	'woocommerce_no_products_found',
	static function () {
		echo '<p class="sd-empty">' . esc_html__( 'Our aftercare products will be available here soon.', 'synergy-integration' ) . '</p>';
	},
	5
);
remove_action( 'woocommerce_no_products_found', 'wc_no_products_found' );

// After "Add to cart" on a product page, show a clear link to the cart (classic notices).
add_filter( 'woocommerce_cart_redirect_after_add', '__return_false' );
