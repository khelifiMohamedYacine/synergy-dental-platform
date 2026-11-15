<?php
/**
 * Idempotent starter content, run by provision.sh with `wp eval-file`.
 *
 * Creates the site structure (pages, menus, delivery options, REST key) and starter treatment
 * and clinic pages whose slugs match the business API seed (backend/app/seed.py), checked
 * afterwards by `wp synergy sync-check`. The clinic group edits the wording in WordPress and the
 * clinic details in the staff dashboard. Earlier sample products and the demo coupon are removed.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create or update a post identified by post type + slug.
 *
 * @param array $args wp_insert_post args (post_type, post_name required).
 * @param array $meta Meta to set.
 */
function sd_upsert_post( array $args, array $meta = array() ): int {
	$existing = get_page_by_path( $args['post_name'], OBJECT, $args['post_type'] );
	$args     = array_merge( array( 'post_status' => 'publish' ), $args );
	if ( $existing ) {
		$args['ID'] = $existing->ID;
		$id         = wp_update_post( $args, true );
	} else {
		$id = wp_insert_post( $args, true );
	}
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return (int) $id;
}

$p = static fn( string ...$paragraphs ): string => implode( "\n\n", array_map( static fn( $t ) => "<!-- wp:paragraph -->\n<p>{$t}</p>\n<!-- /wp:paragraph -->", $paragraphs ) );

// ----------------------------------------------------------------- pages
$pages = array(
	'home'           => array( 'Home', '' ),
	'about'          => array(
		'About us',
		$p(
			'We are a group of four dental clinics offering everyday dentistry, cosmetic treatments and implants, with clear written prices and time to talk through your options.',
			'Our dentists, hygienists and treatment coordinators work together across every clinic, so you get the same standard of care wherever you visit.'
		),
	),
	'contact'        => array(
		'Contact',
		$p( 'The quickest way to reach us is the online form: we will call you back. You can also find each clinic’s phone number and opening hours on its page.' ) . "\n\n<!-- wp:shortcode -->\n[synergy_enquiry_form]\n<!-- /wp:shortcode -->",
	),
	'book'           => array( 'Request an appointment', "<!-- wp:shortcode -->\n[synergy_enquiry_form]\n<!-- /wp:shortcode -->" ),
	'privacy-notice' => array(
		'Privacy notice',
		$p(
			'When you send the enquiry form, your name, email, phone number, preferred clinic, treatment, date and message are sent to our business system so staff can reply. We do not store your IP address with the enquiry. We use your details only to respond to your enquiry.',
			'We keep enquiries only as long as we need them to respond and to meet our legal obligations. To ask for a copy of your data or for an enquiry to be removed, use the contact form and quote your reference.'
		),
	),
);
$page_ids = array();
foreach ( $pages as $slug => [ $title, $content ] ) {
	$page_ids[ $slug ] = sd_upsert_post(
		array(
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
		)
	);
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_ids['home'] );
update_option( 'wp_page_for_privacy_policy', $page_ids['privacy-notice'] );

// Classic cart/checkout shortcodes: the clinic-collection picker uses classic checkout hooks.
foreach ( array(
	'woocommerce_cart_page_id'     => '[woocommerce_cart]',
	'woocommerce_checkout_page_id' => '[woocommerce_checkout]',
) as $option => $shortcode ) {
	$id = (int) get_option( $option );
	if ( $id ) {
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => "<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->",
			)
		);
	}
}

// ----------------------------------------------------------------- treatment categories
$categories = array(
	'general-dentistry'     => 'General dentistry',
	'cosmetic-dentistry'    => 'Cosmetic dentistry',
	'orthodontics'          => 'Orthodontics',
	'restorative-dentistry' => 'Restorative dentistry',
	'emergency-care'        => 'Emergency care',
);
$order = 0;
foreach ( $categories as $slug => $name ) {
	$term = term_exists( $slug, 'treatment_category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'treatment_category', array( 'slug' => $slug ) );
	}
	update_term_meta( (int) $term['term_id'], 'order', $order++ );
}

// ----------------------------------------------------------------- treatments
$faq = static fn( array $pairs ): string => wp_json_encode( array_map( static fn( $p ) => array( 'q' => $p[0], 'a' => $p[1] ), $pairs ) );
$treatments = array(
	array( 'check-up', 'general-dentistry', 'Dental check-up', 'A full examination of teeth and gums with advice.', 6500, '30 minutes',
		'A check-up looks at your teeth, gums, mouth and jaw, and includes a mouth cancer screening. We explain anything we find in plain language and agree what, if anything, needs doing.',
		array( array( 'How often should I have a check-up?', 'Most adults every 6 to 12 months; your dentist will recommend an interval based on your oral health.' ), array( 'Are X-rays included?', 'X-rays are taken only when clinically needed and are included in the check-up price.' ) ) ),
	array( 'hygienist', 'general-dentistry', 'Hygienist appointment', 'Professional scale and polish with home-care advice.', 7500, '45 minutes',
		'Our hygienists remove plaque and tartar, polish away surface stains and show you the cleaning routine that suits your teeth.',
		array( array( 'Does it hurt?', 'Most people feel only mild pressure. Tell the hygienist if anything is uncomfortable and they will adjust.' ) ) ),
	array( 'white-fillings', 'general-dentistry', 'White fillings', 'Tooth-coloured fillings that match your natural teeth.', 12000, '45 minutes',
		'Composite fillings repair decay or damage with a material shaded to match your tooth.',
		array( array( 'How long do white fillings last?', 'Typically 5 to 10 years with good care.' ) ) ),
	array( 'teeth-whitening', 'cosmetic-dentistry', 'Teeth whitening', 'Dentist-supervised home whitening with custom trays.', 29500, '2 visits',
		'We take impressions to make trays that fit you exactly, then supervise a two-week home whitening course with professional gel.',
		array( array( 'Is whitening safe?', 'Yes, when supervised by a dentist using regulated products. Some people notice temporary sensitivity.' ), array( 'How long do results last?', 'Usually one to three years, depending on diet and habits such as coffee or smoking.' ) ) ),
	array( 'composite-bonding', 'cosmetic-dentistry', 'Composite bonding', 'Reshape chipped or uneven teeth in a single visit.', 25000, '1–2 hours',
		'Tooth-coloured resin is sculpted onto the tooth to close small gaps, repair chips or even out edges, usually without drilling.',
		array( array( 'Is bonding reversible?', 'Usually yes, because little or no natural tooth is removed.' ) ) ),
	array( 'porcelain-veneers', 'cosmetic-dentistry', 'Porcelain veneers', 'Thin custom shells that change the shape and shade of teeth.', 75000, '2–3 visits',
		'Veneers are thin porcelain facings made in a laboratory to change the colour, shape or alignment of front teeth.',
		array( array( 'Do veneers need replacing?', 'Porcelain veneers commonly last 10 to 15 years.' ) ) ),
	array( 'clear-aligners', 'orthodontics', 'Clear aligners', 'Nearly invisible removable aligners to straighten teeth.', 199500, '6–18 months',
		'A series of clear, removable aligners gradually moves your teeth. A 3D scan lets you preview the result before you commit.',
		array( array( 'How many hours a day do I wear them?', 'About 22 hours a day; you remove them to eat and brush.' ), array( 'Will I need retainers?', 'Yes, retainers keep teeth in their new position afterwards.' ) ) ),
	array( 'fixed-braces', 'orthodontics', 'Fixed braces', 'Traditional or tooth-coloured braces for complex cases.', 250000, '12–24 months',
		'Fixed braces give precise control for more complex tooth movements and bite corrections.',
		array( array( 'Are tooth-coloured brackets available?', 'Yes, ceramic brackets are available at an additional cost.' ) ) ),
	array( 'dental-implants', 'restorative-dentistry', 'Dental implants', 'A permanent replacement for one or more missing teeth.', 225000, '3–6 months',
		'An implant is a small titanium post placed in the jaw that supports a crown, bridge or denture. Treatment starts with a consultation and 3D scan.',
		array( array( 'Am I suitable for implants?', 'Most healthy adults are. A consultation and scan check bone levels and gum health.' ), array( 'Is the procedure painful?', 'It is done under local anaesthetic; most people describe the recovery as similar to an extraction.' ) ) ),
	array( 'crowns', 'restorative-dentistry', 'Crowns', 'Strengthen and protect a damaged tooth.', 55000, '2 visits',
		'A crown covers a weakened or heavily filled tooth to restore its strength and appearance.',
		array() ),
	array( 'root-canal', 'restorative-dentistry', 'Root canal treatment', 'Treat infection inside a tooth and save it.', 35000, '1–2 visits',
		'Root canal treatment removes infected tissue from inside the tooth, cleans the canals and seals them.',
		array( array( 'Will I need a crown afterwards?', 'Back teeth often do, to protect them from cracking.' ) ) ),
	array( 'emergency-appointment', 'emergency-care', 'Emergency appointment', 'Same-day help for pain, swelling or a broken tooth.', 9500, '30 minutes',
		'If you are in pain, have swelling or have broken a tooth, we keep appointments free every day for emergencies.',
		array( array( 'What if it is out of hours?', 'Call NHS 111 for urgent advice outside opening hours.' ) ) ),
);
$menu_order = 0;
foreach ( $treatments as [ $slug, $cat, $title, $summary, $price, $duration, $body, $faqs ] ) {
	$id = sd_upsert_post(
		array(
			'post_type'    => 'treatment',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_excerpt' => $summary,
			'post_content' => $p( $body, 'Prices are starting prices. Your dentist confirms the cost of your treatment in writing before anything begins.' ),
			'menu_order'   => $menu_order++,
		),
		array(
			'sd_api_slug'   => $slug,
			'sd_summary'    => $summary,
			'sd_duration'   => $duration,
			'sd_price_from' => $price,
			'sd_faqs'       => $faq( $faqs ),
		)
	);
	wp_set_object_terms( $id, $cat, 'treatment_category' );
}

// ----------------------------------------------------------------- clinics
$clinics = array(
	array( 'riverside', 'Riverside Dental Studio', 'Leeds', 'A bright riverside practice with Saturday morning appointments and a dedicated implant suite.' ),
	array( 'harbour-view', 'Harbour View Dental', 'Bristol', 'Overlooking the harbour, with late appointments every Thursday for people who work full time.' ),
	array( 'parkside', 'Parkside Smile Clinic', 'Manchester', 'A calm first-floor clinic by the park, known for its cosmetic and orthodontic team.' ),
	array( 'old-town', 'Old Town Dental Practice', 'York', 'A friendly family practice in a listed building in the heart of the old town.' ),
);
foreach ( $clinics as [ $slug, $title, $city, $intro ] ) {
	sd_upsert_post(
		array(
			'post_type'    => 'clinic',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_excerpt' => $intro,
			'post_content' => $p( $intro, "Our {$city} team includes dentists, a hygienist and a treatment coordinator who will guide you through your options." ),
		),
		array(
			'sd_api_slug' => $slug,
			'sd_map_url'  => 'https://www.openstreetmap.org/search?query=' . rawurlencode( $city ),
		)
	);
}

// ----------------------------------------------------------------- products
$product_cats = array(
	'oral-care' => 'Oral care',
	'whitening' => 'Whitening aftercare',
	'aligners'  => 'Aligner care',
);
$cat_ids = array();
foreach ( $product_cats as $slug => $name ) {
	$term = term_exists( $slug, 'product_cat' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
	}
	$cat_ids[ $slug ] = (int) $term['term_id'];
}
// Remove the sample products (and their photos) created by earlier versions of this script.
// The shop starts empty; products are added in the staff dashboard (Shop) or in WordPress.
$legacy_products = array( 'sensitive-toothpaste', 'whitening-top-up-gel', 'aligner-cleaning-crystals', 'aligner-case', 'interdental-brushes', 'water-flosser', 'soft-toothbrush', 'fluoride-mouthwash' );
foreach ( $legacy_products as $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'product' );
	if ( $post && str_contains( $post->post_title, '(demo)' ) ) {
		wp_delete_post( $post->ID, true );
	}
}
foreach ( get_posts(
	array(
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_sd_media', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
) as $attachment_id ) {
	if ( str_starts_with( (string) get_post_meta( $attachment_id, '_sd_media', true ), 'product-' ) ) {
		wp_delete_attachment( $attachment_id, true );
	}
}
// Orders paid with the old demo payment method (test cards only) were never real.
foreach ( wc_get_orders(
	array(
		'limit'          => -1,
		'payment_method' => 'synergy_demo',
		'return'         => 'ids',
	)
) as $legacy_order ) {
	wc_get_order( $legacy_order )->delete( true );
}
$legacy_coupon = wc_get_coupon_id_by_code( 'smile10' );
if ( $legacy_coupon ) {
	wp_delete_post( $legacy_coupon, true );
}

// ----------------------------------------------------------------- images
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$GLOBALS['sd_media_dir'] = '/provision/media';
$credits                 = array_values(
	array_filter(
		json_decode( (string) file_get_contents( $GLOBALS['sd_media_dir'] . '/credits.json' ), true ),
		static fn( $c ) => ! str_starts_with( $c['file'], 'product-' )
	)
);
$GLOBALS['sd_credit_by'] = array_column( $credits, null, 'file' );

/**
 * Import /provision/media/<name>.jpg once (tracked by meta) and return the attachment ID.
 *
 * @param string $name Image base name.
 * @param string $alt  Alternative text.
 */
function sd_media( string $name, string $alt ): int {
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'meta_key'    => '_sd_media',   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $name,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'      => 'ids',
		)
	);
	if ( $existing ) {
		update_post_meta( $existing[0], '_wp_attachment_image_alt', $alt );
		return (int) $existing[0];
	}
	$tmp = wp_tempnam( $name . '.jpg' );
	copy( $GLOBALS['sd_media_dir'] . "/{$name}.jpg", $tmp );
	$id = media_handle_sideload(
		array(
			'name'     => "{$name}.jpg",
			'tmp_name' => $tmp,
		),
		0,
		$alt
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "Image {$name}: " . $id->get_error_message() );
	}
	update_post_meta( $id, '_sd_media', $name );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	$credit = $GLOBALS['sd_credit_by'][ "{$name}.jpg" ] ?? null;
	if ( $credit ) {
		wp_update_post(
			array(
				'ID'           => $id,
				'post_excerpt' => sprintf( 'Photo: %s, %s (Wikimedia Commons)', $credit['author'] ? $credit['author'] : 'unknown author', $credit['license'] ),
			)
		);
	}
	return (int) $id;
}

$treatment_alts = array(
	'check-up'              => 'A dental treatment room with a patient chair and X-ray viewer',
	'hygienist'             => 'Toothpaste being squeezed onto a toothbrush',
	'white-fillings'        => 'A modern dental chair and instrument unit',
	'teeth-whitening'       => 'Gloved hands preparing a whitening gel syringe and custom trays',
	'composite-bonding'     => 'A patient wearing protective glasses during a cosmetic treatment',
	'porcelain-veneers'     => 'A dentist in a mask and gloves treating a patient',
	'clear-aligners'        => 'Clear aligner trays and a storage case',
	'fixed-braces'          => 'A dental professional in scrubs and gloves at work',
	'dental-implants'       => 'Model of a dental implant with abutment and crown',
	'crowns'                => 'Close-up of implant abutments before crowns are fitted',
	'root-canal'            => 'Panoramic dental X-ray',
	'emergency-appointment' => 'A tray of sterilised dental instruments',
);
foreach ( $treatment_alts as $slug => $alt ) {
	$post = get_page_by_path( $slug, OBJECT, 'treatment' );
	if ( $post ) {
		set_post_thumbnail( $post, sd_media( "treatment-{$slug}", $alt ) );
	}
}
$clinic_alts = array(
	'riverside'    => 'Treatment room at Riverside Dental Studio with wooden cabinets',
	'harbour-view' => 'Bright treatment suite at Harbour View Dental with glass partitions',
	'parkside'     => 'Calm treatment room at Parkside Smile Clinic',
	'old-town'     => 'Treatment room at Old Town Dental Practice with a blue patient chair',
);
foreach ( $clinic_alts as $slug => $alt ) {
	$post = get_page_by_path( $slug, OBJECT, 'clinic' );
	if ( $post ) {
		set_post_thumbnail( $post, sd_media( "clinic-{$slug}", $alt ) );
	}
}
set_post_thumbnail( $page_ids['about'], sd_media( 'about-dentist', 'A dentist in a mask and gloves treating a patient' ) );
update_option( 'synergy_hero_image', sd_media( 'hero', 'A dental surgeon wearing a mask treats a smiling patient' ) );

// Image credits page, generated from the recorded licences.
$rows = '';
foreach ( $credits as $credit ) {
	$rows .= sprintf(
		'<li><strong>%s</strong> by %s. Licence: <a href="%s">%s</a>. <a href="%s">Source</a></li>',
		esc_html( $credit['title'] ),
		esc_html( $credit['author'] ? $credit['author'] : 'unknown author' ),
		esc_url( $credit['license_url'] ? $credit['license_url'] : $credit['source'] ),
		esc_html( $credit['license'] ),
		esc_url( $credit['source'] )
	);
}
$page_ids['image-credits'] = sd_upsert_post(
	array(
		'post_type'    => 'page',
		'post_name'    => 'image-credits',
		'post_title'   => 'Image credits',
		'post_content' => $p( 'Photographs on this site come from Wikimedia Commons under the licences listed below and were resized for the web. Replace them with your own photography at any time in WordPress (Media).' ) . "\n\n<!-- wp:html -->\n<ul class=\"credits\">{$rows}</ul>\n<!-- /wp:html -->",
	)
);

// ----------------------------------------------------------------- delivery and offers
// One UK zone: standard, express, free standard over 30 GBP, and free collection from a clinic.
$zone_id = 0;
foreach ( WC_Shipping_Zones::get_zones() as $z ) {
	if ( 'United Kingdom' === $z['zone_name'] ) {
		$zone_id = (int) $z['id'];
	}
}
$zone = new WC_Shipping_Zone( $zone_id );
$zone->set_zone_name( 'United Kingdom' );
$zone->set_zone_order( 1 );
$zone->set_locations(
	array(
		array(
			'code' => 'GB',
			'type' => 'country',
		),
	)
);
$zone->save();
foreach ( array_keys( $zone->get_shipping_methods() ) as $instance_id ) {
	$zone->delete_shipping_method( $instance_id );
}
$methods = array(
	array( 'flat_rate', array( 'title' => 'Standard delivery (2-3 working days)', 'cost' => '3.95', 'tax_status' => 'none' ) ),
	array( 'flat_rate', array( 'title' => 'Express delivery (next working day)', 'cost' => '6.95', 'tax_status' => 'none' ) ),
	array( 'free_shipping', array( 'title' => 'Free standard delivery (orders over £30)', 'requires' => 'min_amount', 'min_amount' => '30' ) ),
	array( 'local_pickup', array( 'title' => 'Collect from one of our clinics (free)', 'cost' => '0', 'tax_status' => 'none' ) ),
);
foreach ( $methods as [ $type, $settings ] ) {
	$instance_id = $zone->add_shipping_method( $type );
	update_option( "woocommerce_{$type}_{$instance_id}_settings", array_merge( array( 'enabled' => 'yes' ), $settings ) );
}
update_option( 'woocommerce_ship_to_countries', 'specific' );
update_option( 'woocommerce_specific_ship_to_countries', array( 'GB' ) );
update_option( 'woocommerce_allowed_countries', 'specific' );
update_option( 'woocommerce_specific_allowed_countries', array( 'GB' ) );
update_option( 'woocommerce_enable_coupons', 'yes' );
update_option( 'woocommerce_shipping_cost_requires_address', 'no' );
WC_Cache_Helper::get_transient_version( 'shipping', true );

// ----------------------------------------------------------------- menus
$menus = array(
	'Primary' => array(
		'primary',
		array(
			array( 'Treatments', get_post_type_archive_link( 'treatment' ) ),
			array( 'Clinics', get_post_type_archive_link( 'clinic' ) ),
			array( 'About', get_permalink( $page_ids['about'] ) ),
			array( 'Shop', wc_get_page_permalink( 'shop' ) ),
			array( 'Contact', get_permalink( $page_ids['contact'] ) ),
		),
	),
	'Footer'  => array(
		'footer',
		array(
			array( 'About', get_permalink( $page_ids['about'] ) ),
			array( 'Privacy notice', get_permalink( $page_ids['privacy-notice'] ) ),
			array( 'Image credits', get_permalink( $page_ids['image-credits'] ) ),
			array( 'Book an appointment', get_permalink( $page_ids['book'] ) ),
			array( 'Staff sign in', home_url( '/dashboard/' ) ),
		),
	),
);
$locations = get_theme_mod( 'nav_menu_locations', array() );
foreach ( $menus as $name => [ $location, $items ] ) {
	$menu = wp_get_nav_menu_object( $name );
	if ( $menu ) {
		wp_delete_nav_menu( $menu->term_id );
	}
	$menu_id = wp_create_nav_menu( $name );
	foreach ( $items as [ $title, $url ] ) {
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => $title,
				'menu-item-url'    => $url,
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);
	}
	$locations[ $location ] = $menu_id;
}
set_theme_mod( 'nav_menu_locations', $locations );

// ----------------------------------------------------------------- WooCommerce REST key
// The business API reads orders and lets admins manage products and orders with this key (ADR-06). They come from the environment so the
// API container can be given the same values; WooCommerce stores only a hash of the consumer key.
$ck = (string) getenv( 'WC_CONSUMER_KEY' );
$cs = (string) getenv( 'WC_CONSUMER_SECRET' );
if ( $ck && $cs ) {
	global $wpdb;
	$table = $wpdb->prefix . 'woocommerce_api_keys';
	$hash  = wc_api_hash( $ck );
	$admin = get_user_by( 'login', getenv( 'WP_ADMIN_USER' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE description LIKE %s", 'Synergy business API%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'user_id'         => $admin ? $admin->ID : 1,
			'description'     => 'Synergy business API',
			'permissions'     => 'read_write',
			'consumer_key'    => $hash,
			'consumer_secret' => $cs,
			'truncated_key'   => substr( $ck, -7 ),
		)
	);
	WP_CLI::log( 'WooCommerce REST key configured.' );
} else {
	WP_CLI::warning( 'WC_CONSUMER_KEY/SECRET not set: the dashboard shop panel will show "unavailable".' );
}

WP_CLI::success( 'Site content is in place.' );
