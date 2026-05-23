<?php

namespace TuinenBalkon\TBMoneyManager\Service;

class AwinService {

	public const TABLE = 'tbmm_awin_cache';

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

	/**
	 * Verbindingstest: haalt joined programmes op en geeft een synthetisch profiel terug.
	 *
	 * @return array|WP_Error
	 */
	public function get_profile() {
		$pub    = $this->get_publisher_id();
		$result = $this->request( "publishers/{$pub}/programmes", [ 'relationship' => 'joined' ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'id'             => $pub,
			'programmeCount' => count( $result ),
		];
	}

	/**
	 * Transacties voor een heel jaar, per maand opgehaald (API-limiet: 31 dagen).
	 * Verleden maanden worden persistent opgeslagen in de tbmm_awin_cache tabel.
	 * Huidige maand wordt 30 minuten gecached als transient.
	 *
	 * @return array|WP_Error
	 */
	public function get_year_transactions( int $year ) {
		$current_year  = (int) gmdate( 'Y' );
		$current_month = (int) gmdate( 'n' );
		$last_month    = ( $year === $current_year ) ? $current_month : 12;

		$all = [];
		for ( $m = 1; $m <= $last_month; $m++ ) {
			$result = $this->get_month_transactions( $year, $m );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$all = array_merge( $all, $result );
		}

		return $all;
	}

	/**
	 * Wist gecachede data voor een jaar (of de afgelopen 3 jaar als $year null is).
	 */
	public function clear_cache( ?int $year = null ): void {
		global $wpdb;

		$table        = $wpdb->prefix . self::TABLE;
		$current_year = (int) gmdate( 'Y' );

		if ( $year ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, [ 'year' => $year ], [ '%d' ] );
			for ( $m = 1; $m <= 12; $m++ ) {
				delete_transient( "tbmm_awin_tx_{$year}_{$m}" );
			}
			return;
		}

		for ( $y = $current_year - 2; $y <= $current_year; $y++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, [ 'year' => $y ], [ '%d' ] );
			for ( $m = 1; $m <= 12; $m++ ) {
				delete_transient( "tbmm_awin_tx_{$y}_{$m}" );
			}
		}
	}

	/**
	 * Tijdstip waarop een maand voor het laatst is opgehaald, of false.
	 */
	public function get_month_fetched_at( int $year, int $month ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT fetched_at FROM `{$table}` WHERE year = %d AND month = %d", $year, $month )
		);

		if ( ! $row ) {
			return false;
		}

		return (int) strtotime( $row->fetched_at );
	}

	// -------------------------------------------------------------------------

	/** @return array|WP_Error */
	private function get_month_transactions( int $year, int $month ) {
		global $wpdb;

		$current_year  = (int) gmdate( 'Y' );
		$current_month = (int) gmdate( 'n' );
		$is_current    = ( $year === $current_year && $month === $current_month );

		// Huidige maand: transient (30 min).
		if ( $is_current ) {
			$cached = get_transient( "tbmm_awin_tx_{$year}_{$month}" );
			if ( false !== $cached ) {
				return $cached;
			}
		} else {
			// Verleden maand: persistent DB-rij.
			$table = $wpdb->prefix . self::TABLE;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row   = $wpdb->get_row(
				$wpdb->prepare( "SELECT transactions FROM `{$table}` WHERE year = %d AND month = %d", $year, $month )
			);
			if ( $row ) {
				return json_decode( $row->transactions, true ) ?: [];
			}
		}

		// Ophalen via API.
		$start = gmdate( 'Y-m-d\TH:i:s', mktime( 0, 0, 0, $month, 1, $year ) );
		$end   = gmdate( 'Y-m-d\T23:59:59', mktime( 0, 0, 0, $month + 1, 0, $year ) );

		$pub    = $this->get_publisher_id();
		$result = $this->request( "publishers/{$pub}/transactions/", [
			'startDate' => $start,
			'endDate'   => $end,
			'timezone'  => 'Europe/Amsterdam',
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $is_current ) {
			set_transient( "tbmm_awin_tx_{$year}_{$month}", $result, 30 * MINUTE_IN_SECONDS );
		} else {
			$table = $wpdb->prefix . self::TABLE;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->replace(
				$table,
				[
					'year'         => $year,
					'month'        => $month,
					'transactions' => wp_json_encode( $result ),
					'fetched_at'   => current_time( 'mysql', true ),
				],
				[ '%d', '%d', '%s', '%s' ]
			);
		}

		return $result;
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
