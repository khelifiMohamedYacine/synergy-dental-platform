<?php
/**
 * Settings page: API base URL and a connection check. The service key is environment-only.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	static function () {
		register_setting(
			'synergy_integration',
			'synergy_integration_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => static fn( $v ) => array( 'api_base_url' => esc_url_raw( $v['api_base_url'] ?? '' ) ),
				'default'           => array( 'api_base_url' => '' ),
			)
		);
	}
);

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			__( 'Synergy integration', 'synergy-integration' ),
			__( 'Synergy integration', 'synergy-integration' ),
			'manage_options',
			'synergy-integration',
			'synergy_render_settings_page'
		);
	}
);

/**
 * Settings screen.
 */
function synergy_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = get_option( 'synergy_integration_settings', array() );
	$health   = Synergy_Api_Client::request( 'GET', '/health/ready' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Synergy integration', 'synergy-integration' ); ?></h1>
		<p>
			<?php esc_html_e( 'API status:', 'synergy-integration' ); ?>
			<strong><?php echo 200 === $health['status'] ? esc_html__( 'Connected', 'synergy-integration' ) : esc_html__( 'Not reachable', 'synergy-integration' ); ?></strong>
			(<code><?php echo esc_html( Synergy_Api_Client::base_url() ); ?></code>)
		</p>
		<p><?php esc_html_e( 'Service key:', 'synergy-integration' ); ?> <strong><?php echo getenv( 'SERVICE_API_KEY' ) ? esc_html__( 'set in the environment', 'synergy-integration' ) : esc_html__( 'MISSING (set SERVICE_API_KEY)', 'synergy-integration' ); ?></strong></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'synergy_integration' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sd-api"><?php esc_html_e( 'API base URL (optional override)', 'synergy-integration' ); ?></label></th>
					<td><input class="regular-text" id="sd-api" name="synergy_integration_settings[api_base_url]" value="<?php echo esc_attr( $settings['api_base_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( (string) getenv( 'SYNERGY_API_URL' ) ); ?>"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
