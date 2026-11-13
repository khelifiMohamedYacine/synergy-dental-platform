<?php
/**
 * Enquiry / appointment form: [synergy_enquiry_form] (doc ui-design §3.3, ADR-02).
 *
 * Flow: visitor POSTs to WordPress -> nonce + honeypot checked -> server-to-server call to
 * POST /api/v1/enquiries with the service key -> on success redirect (PRG) to a confirmation
 * view; on validation errors re-render with messages; on API failure keep the input and show
 * the clinic phone number. Personal data is never stored in WordPress.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

const SYNERGY_FORM_FIELDS = array(
	'enquiry_type',
	'full_name',
	'email',
	'phone',
	'clinic_slug',
	'treatment_slug',
	'preferred_date',
	'preferred_time_window',
	'message',
	'consent',
);

/**
 * Result of the current request's submission, read by the shortcode.
 *
 * @var array{values: array, errors: array<string,string>, general: string}|null
 */
$GLOBALS['synergy_form_state'] = null;

/**
 * Original visitor IP. Behind Caddy, REMOTE_ADDR is the proxy, which appends the real
 * address as the last X-Forwarded-For entry.
 */
function synergy_client_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$is_private = $remote && ! filter_var( $remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	if ( $is_private && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$hops = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
		$last = end( $hops );
		if ( filter_var( $last, FILTER_VALIDATE_IP ) ) {
			return $last;
		}
	}
	return $remote ? $remote : '0.0.0.0';
}

/**
 * Handle a submitted form before any output, so success can redirect.
 */
function synergy_handle_enquiry_post(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['synergy_enquiry'] ) ) {
		return;
	}
	$values = array();
	foreach ( SYNERGY_FORM_FIELDS as $field ) {
		$raw = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below.
		$values[ $field ] = 'message' === $field ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
	}
	$state = array(
		'values'  => $values,
		'errors'  => array(),
		'general' => '',
	);

	$nonce = isset( $_POST['_sd_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_sd_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'synergy_enquiry' ) ) {
		$state['general'] = __( 'Your session expired. Please check your details and send the form again.', 'synergy-integration' );
		$GLOBALS['synergy_form_state'] = $state;
		return;
	}
	// Honeypot: real visitors never see or fill this field. Pretend success to bots.
	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( add_query_arg( 'sent', '1', get_permalink() ) );
		exit;
	}

	$body = array(
		'enquiry_type' => 'APPOINTMENT' === $values['enquiry_type'] ? 'APPOINTMENT' : 'GENERAL',
		'full_name'    => $values['full_name'],
		'email'        => $values['email'],
		'phone'        => $values['phone'],
		'clinic_slug'  => $values['clinic_slug'],
		'consent'      => '1' === $values['consent'],
	);
	foreach ( array( 'treatment_slug', 'preferred_time_window', 'message' ) as $optional ) {
		if ( '' !== $values[ $optional ] ) {
			$body[ $optional ] = $values[ $optional ];
		}
	}
	if ( 'APPOINTMENT' === $body['enquiry_type'] || '' !== $values['preferred_date'] ) {
		$body['preferred_date'] = '' !== $values['preferred_date'] ? $values['preferred_date'] : null;
	}

	$key = isset( $_POST['_sd_idempotency'] ) ? sanitize_text_field( wp_unslash( $_POST['_sd_idempotency'] ) ) : '';
	$headers = array( 'X-Client-IP' => synergy_client_ip() );
	if ( wp_is_uuid( $key ) ) {
		$headers['Idempotency-Key'] = $key;
	}

	$result = Synergy_Api_Client::request(
		'POST',
		'/enquiries',
		array(
			'body'    => $body,
			'headers' => $headers,
			'service' => true,
		)
	);
	$status = $result['status'];
	$error  = $result['body']['error'] ?? array();

	if ( in_array( $status, array( 200, 201 ), true ) || 409 === $status && 'DUPLICATE_ENQUIRY' === ( $error['code'] ?? '' ) ) {
		$reference = 409 === $status ? ( $error['details'][0]['message'] ?? '' ) : $result['body']['reference'];
		$token     = wp_generate_password( 20, false );
		set_transient(
			'sd_confirm_' . $token,
			array(
				'reference' => $reference,
				'first'     => strtok( $values['full_name'], ' ' ),
				'clinic'    => $values['clinic_slug'],
				'duplicate' => 409 === $status,
			),
			HOUR_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'confirmation', $token, get_permalink() ) );
		exit;
	}

	if ( 422 === $status ) {
		foreach ( $error['details'] ?? array() as $detail ) {
			$state['errors'][ $detail['field'] ] = $detail['message'];
		}
		$state['general'] = __( 'Please correct the highlighted fields.', 'synergy-integration' );
	} elseif ( 429 === $status ) {
		$state['general'] = __( 'We have received several requests from you in a short time. Please wait a few minutes, or call the clinic.', 'synergy-integration' );
	} else {
		$state['general'] = 'unavailable';
	}
	$GLOBALS['synergy_form_state'] = $state;
}
add_action( 'template_redirect', 'synergy_handle_enquiry_post' );

/**
 * Field error markup + aria attributes.
 *
 * @param array  $errors Errors keyed by field.
 * @param string $field  Field.
 */
function synergy_field_aria( array $errors, string $field ): string {
	return isset( $errors[ $field ] )
		? sprintf( ' aria-invalid="true" aria-describedby="%s-error"', esc_attr( $field ) )
		: '';
}

/**
 * Render a field error.
 *
 * @param array  $errors Errors keyed by field.
 * @param string $field  Field.
 */
function synergy_field_error( array $errors, string $field ): string {
	return isset( $errors[ $field ] )
		? sprintf( '<p class="sd-field-error" id="%s-error">%s</p>', esc_attr( $field ), esc_html( $errors[ $field ] ) )
		: '';
}

/**
 * Confirmation view after a successful submission.
 *
 * @param array $c Confirmation data from the transient.
 */
function synergy_render_confirmation( array $c ): string {
	$ref    = Synergy_Api_Client::reference_data();
	$clinic = null;
	foreach ( $ref['clinics'] ?? array() as $item ) {
		if ( $item['slug'] === $c['clinic'] ) {
			$clinic = $item;
		}
	}
	ob_start();
	?>
	<div class="sd-confirmation" tabindex="-1" id="sd-confirmation">
		<h2><?php /* translators: %s: first name */ printf( esc_html__( 'Thank you, %s', 'synergy-integration' ), esc_html( $c['first'] ) ); ?></h2>
		<?php if ( $c['duplicate'] ) : ?>
			<p><?php esc_html_e( 'We already have a recent request from you for this clinic and treatment, so we have not created another one. Your reference is:', 'synergy-integration' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'We have received your request. Your reference is:', 'synergy-integration' ); ?></p>
		<?php endif; ?>
		<p class="sd-reference"><span id="sd-ref"><?php echo esc_html( $c['reference'] ); ?></span>
			<button type="button" class="sd-btn sd-btn-secondary" data-copy="#sd-ref" hidden><?php esc_html_e( 'Copy', 'synergy-integration' ); ?></button></p>
		<h3><?php esc_html_e( 'What happens next', 'synergy-integration' ); ?></h3>
		<ol class="sd-steps">
			<li><?php esc_html_e( 'Our team reviews your request, usually the same working day.', 'synergy-integration' ); ?></li>
			<li><?php esc_html_e( 'We call or email you to answer questions and agree a time.', 'synergy-integration' ); ?></li>
			<li><?php esc_html_e( 'You come in for your consultation.', 'synergy-integration' ); ?></li>
		</ol>
		<?php if ( $clinic ) : ?>
			<p><?php /* translators: 1: clinic name, 2: phone */ printf( esc_html__( 'Questions before then? Call %1$s on %2$s.', 'synergy-integration' ), esc_html( $clinic['name'] ), '<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $clinic['phone'] ) ) . '">' . esc_html( $clinic['phone'] ) . '</a>' ); ?></p>
		<?php endif; ?>
		<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
			<p><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Browse aftercare essentials in our demo shop', 'synergy-integration' ); ?></a></p>
		<?php endif; ?>
		<p class="sd-demo-note"><?php esc_html_e( 'This is a demonstration site: no one will contact you.', 'synergy-integration' ); ?></p>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Shortcode output.
 */
function synergy_enquiry_form_shortcode(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display parameters.
	if ( isset( $_GET['confirmation'] ) ) {
		$confirmation = get_transient( 'sd_confirm_' . sanitize_key( wp_unslash( $_GET['confirmation'] ) ) );
		if ( is_array( $confirmation ) ) {
			return synergy_render_confirmation( $confirmation );
		}
	}
	if ( isset( $_GET['sent'] ) ) {
		return '<p>' . esc_html__( 'Thank you. Your request has been sent.', 'synergy-integration' ) . '</p>';
	}

	$state  = $GLOBALS['synergy_form_state'] ?? null;
	$values = $state['values'] ?? array(
		'enquiry_type'   => 'APPOINTMENT',
		'treatment_slug' => isset( $_GET['treatment'] ) ? sanitize_key( wp_unslash( $_GET['treatment'] ) ) : '',
		'clinic_slug'    => isset( $_GET['clinic'] ) ? sanitize_key( wp_unslash( $_GET['clinic'] ) ) : '',
	);
	// phpcs:enable
	$errors = $state['errors'] ?? array();
	$v      = static fn( string $k ): string => (string) ( $values[ $k ] ?? '' );
	$ref    = Synergy_Api_Client::reference_data();

	wp_enqueue_script( 'synergy-enquiry-form', SYNERGY_INTEGRATION_URL . 'assets/enquiry-form.js', array(), SYNERGY_INTEGRATION_VERSION, array( 'strategy' => 'defer' ) );

	ob_start();
	if ( null === $ref ) {
		echo '<div class="sd-alert sd-alert-error" role="alert">' . esc_html__( 'Online booking is temporarily unavailable. Please call your nearest clinic.', 'synergy-integration' ) . '</div>';
		return (string) ob_get_clean();
	}

	$labels = array(
		'full_name'             => __( 'Full name', 'synergy-integration' ),
		'email'                 => __( 'Email', 'synergy-integration' ),
		'phone'                 => __( 'Phone', 'synergy-integration' ),
		'clinic_slug'           => __( 'Preferred clinic', 'synergy-integration' ),
		'treatment_slug'        => __( 'Treatment', 'synergy-integration' ),
		'preferred_date'        => __( 'Preferred date', 'synergy-integration' ),
		'preferred_time_window' => __( 'Preferred time', 'synergy-integration' ),
		'message'               => __( 'Message', 'synergy-integration' ),
		'consent'               => __( 'Consent', 'synergy-integration' ),
		'enquiry_type'          => __( 'Type of request', 'synergy-integration' ),
	);

	if ( $state && $state['general'] ) {
		if ( 'unavailable' === $state['general'] ) {
			$phones = array_map( static fn( $c ) => esc_html( $c['name'] . ': ' . $c['phone'] ), $ref['clinics'] );
			echo '<div class="sd-alert sd-alert-error" role="alert" tabindex="-1" id="sd-error-summary"><p>' . esc_html__( 'Sorry, we could not send your request just now. Your details are still in the form below, so you can try again in a moment, or call us:', 'synergy-integration' ) . '</p><ul><li>' . implode( '</li><li>', $phones ) . '</li></ul></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		} else {
			echo '<div class="sd-alert sd-alert-error" role="alert" tabindex="-1" id="sd-error-summary"><p>' . esc_html( $state['general'] ) . '</p>';
			if ( $errors ) {
				echo '<ul>';
				foreach ( $errors as $field => $message ) {
					printf( '<li><a href="#%s">%s: %s</a></li>', esc_attr( 'sd-' . $field ), esc_html( $labels[ $field ] ?? $field ), esc_html( $message ) );
				}
				echo '</ul>';
			}
			echo '</div>';
		}
	}
	$today = wp_date( 'Y-m-d' );
	$max   = wp_date( 'Y-m-d', time() + 180 * DAY_IN_SECONDS );
	?>
	<form class="sd-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate
		data-offered="<?php echo esc_attr( wp_json_encode( $ref['offered'] ) ); ?>">
		<input type="hidden" name="synergy_enquiry" value="1">
		<?php wp_nonce_field( 'synergy_enquiry', '_sd_nonce' ); ?>
		<input type="hidden" name="_sd_idempotency" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
		<div class="sd-hp" aria-hidden="true"><label for="sd-website">Website</label><input id="sd-website" name="website" tabindex="-1" autocomplete="off"></div>

		<fieldset class="sd-field">
			<legend><?php echo esc_html( $labels['enquiry_type'] ); ?></legend>
			<label class="sd-radio"><input type="radio" name="enquiry_type" value="APPOINTMENT" <?php checked( 'GENERAL' !== $v( 'enquiry_type' ) ); ?>> <?php esc_html_e( 'Appointment request', 'synergy-integration' ); ?></label>
			<label class="sd-radio"><input type="radio" name="enquiry_type" value="GENERAL" <?php checked( 'GENERAL', $v( 'enquiry_type' ) ); ?>> <?php esc_html_e( 'General enquiry', 'synergy-integration' ); ?></label>
		</fieldset>

		<?php
		foreach ( array(
			'full_name' => array( 'text', 'name' ),
			'email'     => array( 'email', 'email' ),
			'phone'     => array( 'tel', 'tel' ),
		) as $field => [ $type, $autocomplete ] ) :
			?>
			<div class="sd-field">
				<label for="sd-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $labels[ $field ] ); ?> <span class="sd-req" aria-hidden="true">*</span></label>
				<input id="sd-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" type="<?php echo esc_attr( $type ); ?>"
					autocomplete="<?php echo esc_attr( $autocomplete ); ?>" required value="<?php echo esc_attr( $v( $field ) ); ?>"<?php echo synergy_field_aria( $errors, $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php if ( 'phone' === $field ) : ?>
					<p class="sd-hint"><?php esc_html_e( 'UK mobile or landline. For this demo, please use a fictional number such as 07700 900123.', 'synergy-integration' ); ?></p>
				<?php elseif ( 'email' === $field ) : ?>
					<p class="sd-hint"><?php esc_html_e( 'For this demo, please use a fictional address such as you@example.test.', 'synergy-integration' ); ?></p>
				<?php endif; ?>
				<?php echo synergy_field_error( $errors, $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endforeach; ?>

		<div class="sd-field">
			<label for="sd-clinic_slug"><?php echo esc_html( $labels['clinic_slug'] ); ?> <span class="sd-req" aria-hidden="true">*</span></label>
			<select id="sd-clinic_slug" name="clinic_slug" required<?php echo synergy_field_aria( $errors, 'clinic_slug' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<option value=""><?php esc_html_e( 'Choose a clinic', 'synergy-integration' ); ?></option>
				<?php foreach ( $ref['clinics'] as $clinic ) : ?>
					<option value="<?php echo esc_attr( $clinic['slug'] ); ?>" <?php selected( $v( 'clinic_slug' ), $clinic['slug'] ); ?>><?php echo esc_html( $clinic['name'] . ' – ' . $clinic['city'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php echo synergy_field_error( $errors, 'clinic_slug' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="sd-field">
			<label for="sd-treatment_slug"><?php echo esc_html( $labels['treatment_slug'] ); ?></label>
			<select id="sd-treatment_slug" name="treatment_slug"<?php echo synergy_field_aria( $errors, 'treatment_slug' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<option value=""><?php esc_html_e( 'Not sure yet', 'synergy-integration' ); ?></option>
				<?php foreach ( $ref['treatments'] as $treatment ) : ?>
					<option value="<?php echo esc_attr( $treatment['slug'] ); ?>" <?php selected( $v( 'treatment_slug' ), $treatment['slug'] ); ?>><?php echo esc_html( $treatment['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php echo synergy_field_error( $errors, 'treatment_slug' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="sd-field-row" data-appointment-only>
			<div class="sd-field">
				<label for="sd-preferred_date"><?php echo esc_html( $labels['preferred_date'] ); ?> <span class="sd-req" aria-hidden="true">*</span></label>
				<input id="sd-preferred_date" name="preferred_date" type="date" min="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $v( 'preferred_date' ) ); ?>"<?php echo synergy_field_aria( $errors, 'preferred_date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo synergy_field_error( $errors, 'preferred_date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="sd-field">
				<label for="sd-preferred_time_window"><?php echo esc_html( $labels['preferred_time_window'] ); ?></label>
				<select id="sd-preferred_time_window" name="preferred_time_window">
					<option value=""><?php esc_html_e( 'Any time', 'synergy-integration' ); ?></option>
					<?php
					foreach ( array(
						'MORNING'   => __( 'Morning', 'synergy-integration' ),
						'AFTERNOON' => __( 'Afternoon', 'synergy-integration' ),
						'EVENING'   => __( 'Evening', 'synergy-integration' ),
					) as $value => $label ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $v( 'preferred_time_window' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="sd-field">
			<label for="sd-message"><?php echo esc_html( $labels['message'] ); ?></label>
			<textarea id="sd-message" name="message" rows="4" maxlength="2000" aria-describedby="sd-message-count"<?php echo isset( $errors['message'] ) ? ' aria-invalid="true"' : ''; ?>><?php echo esc_textarea( $v( 'message' ) ); ?></textarea>
			<p class="sd-hint" id="sd-message-count"><?php esc_html_e( 'Please do not include medical details. Up to 2,000 characters.', 'synergy-integration' ); ?></p>
			<?php echo synergy_field_error( $errors, 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="sd-field">
			<label class="sd-check"><input type="checkbox" id="sd-consent" name="consent" value="1" required <?php checked( '1', $v( 'consent' ) ); ?><?php echo synergy_field_aria( $errors, 'consent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
				printf(
					/* translators: %s: privacy notice link */
					esc_html__( 'I agree to be contacted about my enquiry, as described in the %s. *', 'synergy-integration' ),
					'<a href="' . esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy-notice/' ) ) . '">' . esc_html__( 'privacy notice', 'synergy-integration' ) . '</a>'
				);
				?>
			</label>
			<?php echo synergy_field_error( $errors, 'consent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<button type="submit" class="sd-btn" data-sending="<?php esc_attr_e( 'Sending…', 'synergy-integration' ); ?>"><?php esc_html_e( 'Send request', 'synergy-integration' ); ?></button>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'synergy_enquiry_form', 'synergy_enquiry_form_shortcode' );
