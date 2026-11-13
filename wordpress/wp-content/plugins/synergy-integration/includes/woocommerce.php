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
		$this->has_fields         = true;
		$this->supports           = array( 'products' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title', __( 'Credit or debit card (demo, test cards only)', 'synergy-integration' ) );
		$this->description = $this->get_option( 'description', __( 'Payments are simulated: nothing is charged and nothing is delivered.', 'synergy-integration' ) );
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
				'default' => __( 'Credit or debit card (demo, test cards only)', 'synergy-integration' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'synergy-integration' ),
				'type'    => 'textarea',
				'default' => __( 'This is a demonstration shop. Your order will be marked as paid, but nothing will be charged or delivered.', 'synergy-integration' ),
			),
		);
	}

	/**
	 * Published test card numbers (Stripe/Adyen style). Anything else is refused, so a visitor
	 * cannot accidentally type a real card into a demo site.
	 */
	const TEST_CARDS = array(
		'4242424242424242' => 'Visa',
		'4000056655665556' => 'Visa debit',
		'5555555555554444' => 'Mastercard',
		'2223003122003222' => 'Mastercard',
		'378282246310005'  => 'American Express',
	);

	/**
	 * Card form shown under the payment method. Field names are prefixed and autocomplete is off
	 * so browsers do not offer to save card details for a demo site.
	 */
	public function payment_fields(): void {
		echo wp_kses_post( wpautop( $this->description ) );
		?>
		<fieldset class="sd-card" id="sd-card-fields">
			<legend class="screen-reader-text"><?php esc_html_e( 'Card details', 'synergy-integration' ); ?></legend>
			<p class="sd-card-test"><?php esc_html_e( 'Test mode: use card 4242 4242 4242 4242, any future expiry date and any 3-digit CVC. Real cards are refused.', 'synergy-integration' ); ?></p>
			<p class="form-row form-row-wide">
				<label for="sd_card_name"><?php esc_html_e( 'Name on card', 'synergy-integration' ); ?> <abbr class="required" title="required">*</abbr></label>
				<input id="sd_card_name" name="sd_card_name" class="input-text" type="text" autocomplete="off" spellcheck="false">
			</p>
			<p class="form-row form-row-wide">
				<label for="sd_card_number"><?php esc_html_e( 'Card number', 'synergy-integration' ); ?> <abbr class="required" title="required">*</abbr></label>
				<input id="sd_card_number" name="sd_card_number" class="input-text" type="text" inputmode="numeric" autocomplete="off" placeholder="4242 4242 4242 4242" maxlength="23">
			</p>
			<p class="form-row form-row-first">
				<label for="sd_card_expiry"><?php esc_html_e( 'Expiry (MM/YY)', 'synergy-integration' ); ?> <abbr class="required" title="required">*</abbr></label>
				<input id="sd_card_expiry" name="sd_card_expiry" class="input-text" type="text" inputmode="numeric" autocomplete="off" placeholder="MM/YY" maxlength="7">
			</p>
			<p class="form-row form-row-last">
				<label for="sd_card_cvc"><?php esc_html_e( 'Security code (CVC)', 'synergy-integration' ); ?> <abbr class="required" title="required">*</abbr></label>
				<input id="sd_card_cvc" name="sd_card_cvc" class="input-text" type="password" inputmode="numeric" autocomplete="off" maxlength="4">
			</p>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Luhn checksum.
	 *
	 * @param string $digits Card number digits.
	 */
	private static function luhn_ok( string $digits ): bool {
		$sum = 0;
		foreach ( array_reverse( str_split( $digits ) ) as $i => $d ) {
			$d = (int) $d;
			if ( $i % 2 ) {
				$d *= 2;
				$d  = $d > 9 ? $d - 9 : $d;
			}
			$sum += $d;
		}
		return 0 === $sum % 10;
	}

	/**
	 * Validate the card form. Values are only checked here; they are never stored or logged.
	 */
	public function validate_fields(): bool {
		// Nonce checked by WooCommerce's checkout handler before gateways validate.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$name   = isset( $_POST['sd_card_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sd_card_name'] ) ) : '';
		$number = isset( $_POST['sd_card_number'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['sd_card_number'] ) ) ) : '';
		$expiry = isset( $_POST['sd_card_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['sd_card_expiry'] ) ) : '';
		$cvc    = isset( $_POST['sd_card_cvc'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['sd_card_cvc'] ) ) ) : '';
		// phpcs:enable
		$ok = true;
		if ( strlen( $name ) < 2 ) {
			wc_add_notice( __( 'Enter the name on the card.', 'synergy-integration' ), 'error', array( 'id' => 'sd_card_name' ) );
			$ok = false;
		}
		if ( strlen( $number ) < 13 || ! self::luhn_ok( $number ) ) {
			wc_add_notice( __( 'Enter a valid card number.', 'synergy-integration' ), 'error', array( 'id' => 'sd_card_number' ) );
			$ok = false;
		} elseif ( ! isset( self::TEST_CARDS[ $number ] ) ) {
			wc_add_notice( __( 'This is a demonstration shop: only test cards are accepted (for example 4242 4242 4242 4242). Never enter a real card here.', 'synergy-integration' ), 'error', array( 'id' => 'sd_card_number' ) );
			$ok = false;
		}
		if ( ! preg_match( '#^\s*(0[1-9]|1[0-2])\s*/\s*(\d{2}|\d{4})\s*$#', $expiry, $m ) ) {
			wc_add_notice( __( 'Enter the expiry date as MM/YY.', 'synergy-integration' ), 'error', array( 'id' => 'sd_card_expiry' ) );
			$ok = false;
		} else {
			$year = (int) ( strlen( $m[2] ) === 2 ? '20' . $m[2] : $m[2] );
			if ( mktime( 0, 0, 0, (int) $m[1] + 1, 1, $year ) <= time() ) {
				wc_add_notice( __( 'This card has expired.', 'synergy-integration' ), 'error', array( 'id' => 'sd_card_expiry' ) );
				$ok = false;
			}
		}
		$cvc_length = '3' === substr( $number, 0, 1 ) ? 4 : 3;
		if ( strlen( $cvc ) !== $cvc_length ) {
			/* translators: %d: number of digits */
			wc_add_notice( sprintf( __( 'Enter the %d-digit security code.', 'synergy-integration' ), $cvc_length ), 'error', array( 'id' => 'sd_card_cvc' ) );
			$ok = false;
		}
		return $ok;
	}

	/**
	 * Mark the order paid and go to the thank-you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ): array {
		$order  = wc_get_order( $order_id );
		$number = isset( $_POST['sd_card_number'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['sd_card_number'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$brand  = self::TEST_CARDS[ $number ] ?? __( 'Card', 'synergy-integration' );
		$order->payment_complete( 'DEMO-' . strtoupper( wp_generate_password( 10, false ) ) );
		/* translators: 1: card brand, 2: last four digits */
		$order->add_order_note( sprintf( __( 'Demo payment approved: %1$s ending %2$s. No money taken.', 'synergy-integration' ), $brand, substr( $number, -4 ) ) );
		$order->update_meta_data( '_sd_card_summary', $brand . ' •••• ' . substr( $number, -4 ) );
		$order->save();
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
 * Collection clinic and card summary on the thank-you page, emails and the admin order screen.
 *
 * @param WC_Order $order Order.
 */
function synergy_order_extras( WC_Order $order ): void {
	$clinic = (string) $order->get_meta( '_sd_collection_clinic' );
	$card   = (string) $order->get_meta( '_sd_card_summary' );
	if ( $clinic ) {
		printf( '<p class="sd-order-extra"><strong>%s</strong> %s</p>', esc_html__( 'Collect from:', 'synergy-integration' ), esc_html( $clinic ) );
	}
	if ( $card ) {
		printf( '<p class="sd-order-extra"><strong>%s</strong> %s %s</p>', esc_html__( 'Paid with:', 'synergy-integration' ), esc_html( $card ), esc_html__( '(demo, nothing charged)', 'synergy-integration' ) );
	}
}
add_action( 'woocommerce_order_details_after_order_table', 'synergy_order_extras' );
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'synergy_order_extras' );
add_action( 'woocommerce_email_after_order_table', 'synergy_order_extras' );

/*
 * Demo offer banner (code SMILE10, created by provisioning) on the shop, product and cart pages.
 */
function synergy_offer_banner(): void {
	if ( ! wc_coupons_enabled() ) {
		return;
	}
	echo '<div class="sd-offer" role="note"><strong>' . esc_html__( 'Aftercare offer:', 'synergy-integration' ) . '</strong> ' .
		wp_kses( __( 'use code <code>SMILE10</code> for 10% off. Free delivery on orders over £30, or collect free from any clinic.', 'synergy-integration' ), array( 'code' => array() ) ) . '</div>';
}
add_action( 'woocommerce_before_shop_loop', 'synergy_offer_banner', 5 );
add_action( 'woocommerce_before_cart', 'synergy_offer_banner' );
add_action( 'woocommerce_before_single_product', 'synergy_offer_banner' );

// After "Add to cart" on a product page, show a clear link to the cart (classic notices).
add_filter( 'woocommerce_cart_redirect_after_add', '__return_false' );
