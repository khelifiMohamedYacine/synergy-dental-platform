<?php
/**
 * WooCommerce customisations (FR-SHOP): demo payment gateway, demo-store notice,
 * "Recommended after" product field, and related products on treatment pages.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

/**
 * A payment method that accepts every order without taking money.
 */
class Synergy_Demo_Gateway extends WC_Payment_Gateway {

	/**
	 * Set up the gateway.
	 */
	public function __construct() {
		$this->id                 = 'synergy_demo';
		$this->method_title       = __( 'Demo payment', 'synergy-integration' );
		$this->method_description = __( 'Simulated payment for the demonstration shop. No money is taken.', 'synergy-integration' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title', __( 'Demo payment (no money is taken)', 'synergy-integration' ) );
		$this->description = $this->get_option( 'description', __( 'This is a demonstration shop. Your order will be marked as paid, but nothing will be charged or delivered.', 'synergy-integration' ) );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Admin settings.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable', 'synergy-integration' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'synergy-integration' ),
				'type'    => 'text',
				'default' => __( 'Demo payment (no money is taken)', 'synergy-integration' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'synergy-integration' ),
				'type'    => 'textarea',
				'default' => __( 'This is a demonstration shop. Your order will be marked as paid, but nothing will be charged or delivered.', 'synergy-integration' ),
			),
		);
	}

	/**
	 * Mark the order paid and go to the thank-you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		$order->payment_complete( 'DEMO-' . strtoupper( wp_generate_password( 10, false ) ) );
		$order->add_order_note( __( 'Demo payment: no money taken.', 'synergy-integration' ) );
		WC()->cart->empty_cart();
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}

add_filter(
	'woocommerce_payment_gateways',
	static function ( array $gateways ): array {
		$gateways[] = 'Synergy_Demo_Gateway';
		return $gateways;
	}
);

// Site-wide "demo store" notice.
add_filter( 'woocommerce_demo_store', static fn() => '<p class="woocommerce-store-notice demo_store" role="note">' . esc_html__( 'Demo store: no real payments or deliveries.', 'synergy-integration' ) . '</p>' );
add_filter( 'pre_option_woocommerce_demo_store', static fn() => 'yes' );

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
