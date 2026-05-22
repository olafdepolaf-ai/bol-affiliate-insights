<?php

namespace TuinenBalkon\TBMoneyManager\Service;

class AwinService {

	private const API_BASE = 'https://api.awin.com/';

	public function get_api_token(): string {
		return (string) get_option( 'tbmm_awin_api_token', '' );
	}

	public function get_publisher_id(): string {
		return (string) get_option( 'tbmm_awin_publisher_id', '' );
	}

	public function has_credentials(): bool {
		return ! empty( $this->get_api_token() ) && ! empty( $this->get_publisher_id() );
	}

	/** @return array|WP_Error */
	public function get_profile() {
		$pub = $this->get_publisher_id();
		return $this->request( "publishers/{$pub}/profile" );
	}

	/**
	 * Transacties voor een bepaalde maand.
	 *
	 * @return array|WP_Error
	 */
	public function get_transactions( int $year, int $month ) {
		$cache_key = "tbmm_awin_tx_{$year}_{$month}";
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$start = gmdate( 'Y-m-d\TH:i:s', mktime( 0, 0, 0, $month, 1, $year ) );
		$end   = gmdate( 'Y-m-d\T23:59:59', mktime( 0, 0, 0, $month + 1, 0, $year ) );

		$pub    = $this->get_publisher_id();
		$result = $this->request( "publishers/{$pub}/transactions/", [
			'startDate' => $start,
			'endDate'   => $end,
			'timezone'  => 'Europe/Amsterdam',
		] );

		if ( ! is_wp_error( $result ) ) {
			$ttl = ( $year === (int) gmdate( 'Y' ) && $month === (int) gmdate( 'n' ) ) ? 30 * MINUTE_IN_SECONDS : DAY_IN_SECONDS;
			set_transient( $cache_key, $result, $ttl );
		}

		return $result;
	}

	/**
	 * Geaggregeerd rapport per adverteerder over een maand.
	 *
	 * @return array|WP_Error
	 */
	public function get_advertiser_report( int $year, int $month ) {
		$cache_key = "tbmm_awin_report_{$year}_{$month}";
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$start = gmdate( 'Y-m-d', mktime( 0, 0, 0, $month, 1, $year ) );
		$end   = gmdate( 'Y-m-d', mktime( 0, 0, 0, $month + 1, 0, $year ) );

		$pub    = $this->get_publisher_id();
		$result = $this->request( "publishers/{$pub}/reports/advertiser", [
			'startDate' => $start,
			'endDate'   => $end,
		] );

		if ( ! is_wp_error( $result ) ) {
			$ttl = ( $year === (int) gmdate( 'Y' ) && $month === (int) gmdate( 'n' ) ) ? 2 * HOUR_IN_SECONDS : DAY_IN_SECONDS;
			set_transient( $cache_key, $result, $ttl );
		}

		return $result;
	}

	public function get_transactions_fetched_at( int $year, int $month ): int|false {
		return get_option( "tbmm_awin_tx_fetched_{$year}_{$month}", false );
	}

	public function clear_cache( ?int $year = null, ?int $month = null ): void {
		if ( $year && $month ) {
			delete_transient( "tbmm_awin_tx_{$year}_{$month}" );
			delete_transient( "tbmm_awin_report_{$year}_{$month}" );
			return;
		}
		// Wis alle Awin-transients voor de afgelopen 3 jaar.
		$current_year = (int) gmdate( 'Y' );
		for ( $y = $current_year - 2; $y <= $current_year; $y++ ) {
			for ( $m = 1; $m <= 12; $m++ ) {
				delete_transient( "tbmm_awin_tx_{$y}_{$m}" );
				delete_transient( "tbmm_awin_report_{$y}_{$m}" );
			}
		}
	}

	/**
	 * Voert een GET-request uit naar de Awin API.
	 *
	 * @param  string $path   Pad na de base URL.
	 * @param  array  $params Query-parameters.
	 * @return array|WP_Error Decoded JSON-array bij succes, WP_Error bij fout.
	 */
	private function request( string $path, array $params = [] ) {
		$token = $this->get_api_token();
		if ( empty( $token ) ) {
			return new \WP_Error( 'no_token', __( 'Geen Awin API-token ingesteld.', 'tbmm' ) );
		}

		$url = self::API_BASE . ltrim( $path, '/' );
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get( $url, [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 401 ) {
			return new \WP_Error( 'unauthorized', __( 'Ongeldige API-token of geen toegang.', 'tbmm' ) );
		}

		if ( $code !== 200 ) {
			return new \WP_Error( 'api_error', sprintf(
				/* translators: 1: HTTP status code, 2: response body snippet */
				__( 'Awin API fout (HTTP %1$s): %2$s', 'tbmm' ),
				$code,
				wp_strip_all_tags( substr( $body, 0, 200 ) )
			) );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_json', __( 'Ongeldig JSON-antwoord van Awin API.', 'tbmm' ) );
		}

		return $data;
	}
}
