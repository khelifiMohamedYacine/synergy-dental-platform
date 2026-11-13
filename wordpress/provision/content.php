<?php
/**
 * Idempotent demo content, run by provision.sh with `wp eval-file`.
 *
 * Treatment and clinic slugs match the business API seed (backend/app/seed.py), which is
 * checked afterwards by `wp synergy sync-check`. Everything here is fictional.
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
			'Synergy Dental (demo) is a fictional group of four dental clinics created for a software demonstration. Our imaginary teams offer everyday dentistry, cosmetic treatments and implants with clear, written prices.',
			'This website, its clinics, people and products do not exist. It is an independent technical demonstration and is not affiliated with, commissioned by, or endorsed by Synergy Dental Group.'
		),
	),
	'contact'        => array(
		'Contact',
		$p( 'The quickest way to reach us is the online form: we will call you back. You can also find each clinic’s (fictional) phone number on its page.' ) . "\n\n<!-- wp:shortcode -->\n[synergy_enquiry_form]\n<!-- /wp:shortcode -->",
	),
	'book'           => array( 'Request an appointment', "<!-- wp:shortcode -->\n[synergy_enquiry_form]\n<!-- /wp:shortcode -->" ),
	'privacy-notice' => array(
		'Privacy notice',
		$p(
			'This is a demonstration site. Please enter only fictional details: use an address ending in example.test and a phone number from the 07700 900xxx range.',
			'When you send the enquiry form, your name, email, phone number, preferred clinic, treatment, date and message are sent to our business system so staff can reply. We do not store your IP address with the enquiry. We use your details only to respond to your enquiry.',
			'Demo data may be deleted at any time. To ask for an enquiry to be removed, use the contact form and quote your reference.'
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

// Classic cart/checkout shortcodes: the demo gateway is a classic gateway.
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
		array( array( 'What if it is out of hours?', 'Call NHS 111 for urgent advice outside opening hours. (In this demo, no clinic is real.)' ) ) ),
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
			'post_content' => $p( $intro, "Our (fictional) {$city} team includes dentists, a hygienist and a treatment coordinator who will guide you through your options." ),
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
$products = array(
	// slug, name, price, category, recommended after, featured, short description
	array( 'sensitive-toothpaste', 'Sensitive Relief Toothpaste (demo)', '6.50', 'whitening', 'teeth-whitening', true, 'Gentle fluoride toothpaste to ease sensitivity after whitening.' ),
	array( 'whitening-top-up-gel', 'Whitening Top-up Gel (demo)', '24.00', 'whitening', 'teeth-whitening', false, 'Two syringes of top-up gel for your custom trays.' ),
	array( 'aligner-cleaning-crystals', 'Aligner Cleaning Crystals (demo)', '14.99', 'aligners', 'clear-aligners', true, 'A month of cleaning crystals to keep aligners clear and fresh.' ),
	array( 'aligner-case', 'Aligner Travel Case (demo)', '8.00', 'aligners', 'clear-aligners', false, 'A ventilated, pocket-sized case for your aligners.' ),
	array( 'interdental-brushes', 'Interdental Brushes, Mixed Sizes (demo)', '5.25', 'oral-care', 'dental-implants', true, 'Soft brushes for cleaning around implants and between teeth.' ),
	array( 'water-flosser', 'Cordless Water Flosser (demo)', '49.00', 'oral-care', 'fixed-braces', false, 'Rechargeable water flosser, ideal around braces and bridges.' ),
	array( 'soft-toothbrush', 'Ultra-soft Toothbrush (demo)', '4.50', 'oral-care', 'hygienist', false, 'Extra-soft bristles recommended after a hygienist visit.' ),
	array( 'fluoride-mouthwash', 'Fluoride Mouthwash 500 ml (demo)', '5.99', 'oral-care', 'white-fillings', false, 'Alcohol-free mouthwash with fluoride to help protect fillings.' ),
);
foreach ( $products as [ $slug, $name, $price, $cat, $after, $featured, $short ] ) {
	$existing = get_page_by_path( $slug, OBJECT, 'product' );
	$product  = $existing ? wc_get_product( $existing->ID ) : new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_slug( $slug );
	$product->set_status( 'publish' );
	$product->set_regular_price( $price );
	$product->set_short_description( $short );
	$product->set_description( $short . ' This is a fictional product in a demonstration shop: nothing will be charged or delivered.' );
	$product->set_category_ids( array( $cat_ids[ $cat ] ) );
	$product->set_featured( $featured );
	$product->set_virtual( true ); // No shipping step in the demo checkout.
	$product->set_manage_stock( false );
	$product->set_stock_status( 'instock' );
	$product->update_meta_data( '_sd_recommended_after', $after );
	$product->save();
}

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
			array( 'Book an appointment', get_permalink( $page_ids['book'] ) ),
			array( 'Staff dashboard (demo)', home_url( '/dashboard/' ) ),
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

// ----------------------------------------------------------------- WooCommerce read-only REST key
// The business API reads orders with these keys (ADR-06). They come from the environment so the
// API container can be given the same values; WooCommerce stores only a hash of the consumer key.
$ck = (string) getenv( 'WC_CONSUMER_KEY' );
$cs = (string) getenv( 'WC_CONSUMER_SECRET' );
if ( $ck && $cs ) {
	global $wpdb;
	$table = $wpdb->prefix . 'woocommerce_api_keys';
	$hash  = wc_api_hash( $ck );
	$admin = get_user_by( 'login', getenv( 'WP_ADMIN_USER' ) );
	$wpdb->delete( $table, array( 'description' => 'Synergy business API (read-only)' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'user_id'         => $admin ? $admin->ID : 1,
			'description'     => 'Synergy business API (read-only)',
			'permissions'     => 'read',
			'consumer_key'    => $hash,
			'consumer_secret' => $cs,
			'truncated_key'   => substr( $ck, -7 ),
		)
	);
	WP_CLI::log( 'WooCommerce read-only REST key configured.' );
} else {
	WP_CLI::warning( 'WC_CONSUMER_KEY/SECRET not set: the dashboard shop panel will show "unavailable".' );
}

WP_CLI::success( 'Demo content is in place.' );
