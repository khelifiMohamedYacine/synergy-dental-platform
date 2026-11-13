<?php
/**
 * Server-to-server client for the FastAPI business API (ADR-02, doc 05 §7).
 *
 * The service key is read from the environment only, never from the options table, so it is
 * not exposed through the admin screens or a database export.
 *
 * @package SynergyIntegration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around wp_remote_request() with the API's error envelope.
 */
final class Synergy_Api_Client {

	const TIMEOUT       = 5;
	const CACHE_KEY     = 'sd_reference_data';
	const STALE_KEY     = 'sd_reference_data_stale';
	const CACHE_SECONDS = 600;

	/**
	 * Base URL such as http://api:8000/api/v1.
	 */
	public static function base_url(): string {
		$settings = get_option( 'synergy_integration_settings', array() );
		$url      = ! empty( $settings['api_base_url'] ) ? $settings['api_base_url'] : getenv( 'SYNERGY_API_URL' );
		return untrailingslashit( $url ? $url : 'http://api:8000/api/v1' );
	}

	/**
	 * Perform a request. Returns array{status:int, body:array|null, error:?string}.
	 *
	 * @param string $method  HTTP method.
	 * @param string $path    Path below the base URL.
	 * @param array  $args    body (array), headers (array), service (bool).
	 */
	public static function request( string $method, string $path, array $args = array() ): array {
		$headers = array_merge(
			array(
				'Accept'     => 'application/json',
				'User-Agent' => 'synergy-integration/' . SYNERGY_INTEGRATION_VERSION,
			),
			$args['headers'] ?? array()
		);
		if ( ! empty( $args['service'] ) ) {
			$headers['X-Service-Key'] = (string) getenv( 'SERVICE_API_KEY' );
		}
		$request = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
		);
		if ( isset( $args['body'] ) ) {
			$request['headers']['Content-Type'] = 'application/json';
			$request['body']                    = wp_json_encode( $args['body'] );
		}
		$response = wp_remote_request( self::base_url() . $path, $request );
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'body'   => null,
				'error'  => $response->get_error_message(),
			);
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => is_array( $body ) ? $body : null,
			'error'  => null,
		);
	}

	/**
	 * Active clinics and the treatments each offers, cached for 10 minutes.
	 * If the API is down, the last good copy is served (stale-while-error, doc 05 §7).
	 *
	 * @return array{clinics: array, treatments: array, offered: array<string, string[]>}|null
	 */
	public static function reference_data(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$clinics    = self::request( 'GET', '/clinics' );
		$treatments = self::request( 'GET', '/treatments' );
		if ( 200 !== $clinics['status'] || 200 !== $treatments['status'] ) {
			$stale = get_option( self::STALE_KEY );
			return is_array( $stale ) ? $stale : null;
		}
		$by_id = array();
		foreach ( $treatments['body'] as $t ) {
			$by_id[ $t['id'] ] = $t;
		}
		$offered = array();
		foreach ( $clinics['body'] as $c ) {
			$detail                   = self::request( 'GET', '/clinics/' . (int) $c['id'] );
			$ids                      = 200 === $detail['status'] ? $detail['body']['treatment_ids'] : array();
			$offered[ $c['slug'] ] = array_values(
				array_filter(
					array_map( static fn( $id ) => $by_id[ $id ]['slug'] ?? null, $ids )
				)
			);
		}
		$data = array(
			'clinics'    => $clinics['body'],
			'treatments' => $treatments['body'],
			'offered'    => $offered,
		);
		set_transient( self::CACHE_KEY, $data, self::CACHE_SECONDS );
		update_option( self::STALE_KEY, $data, false );
		return $data;
	}
}
