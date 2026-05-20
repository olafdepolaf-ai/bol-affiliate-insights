<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiClient {

	const API_BASE_URL          = 'https://api.bol.com/marketing/affiliate/reports/v2';
	const CACHE_DURATION_SECONDS = HOUR_IN_SECONDS;

	private ApiAuthService $auth_service;

	public function __construct( ApiAuthService $auth_service ) {
		$this->auth_service = $auth_service;
	}

	private function make_request( string $endpoint, array $args = array(), string $method = 'GET' ) {
		$token = $this->auth_service->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}
		if ( ! $token ) {
			return new \WP_Error( 'token_unavailable', 'Could not retrieve a valid access token.' );
		}

		$url          = self::API_BASE_URL . $endpoint;
		$request_args = array(
			'method'  => $method,
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
			'timeout' => 30,
		);

		if ( ! empty( $args ) ) {
			if ( 'GET' === $method ) {
				$url = add_query_arg( $args, $url );
			} else {
				$request_args['body']                      = json_encode( $args );
				$request_args['headers']['Content-Type']   = 'application/json';
			}
		}

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'api_request_failed',
				'API request failed for ' . $endpoint . '. Status: ' . $response_code,
				array( 'status' => $response_code, 'body' => $body )
			);
		}

		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new \WP_Error( 'invalid_api_response', 'Invalid JSON response from API for ' . $endpoint, array( 'body' => $body ) );
		}

		return $data;
	}

	public function get_orders_report( string $start_date, string $end_date ) {
		$cache_key   = 'bol_report_orders_' . md5( $start_date . '_' . $end_date );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$fresh_data = $this->make_request( '/order-report', array( 'startDate' => $start_date, 'endDate' => $end_date ) );

		if ( ! is_wp_error( $fresh_data ) && ! empty( $fresh_data ) ) {
			set_transient( $cache_key, $fresh_data, self::CACHE_DURATION_SECONDS );
		}

		return $fresh_data;
	}

	public function get_commission_revenue_report( string $start_date, string $end_date ) {
		$cache_key   = 'bol_report_commission_' . md5( $start_date . '_' . $end_date );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$fresh_data = $this->make_request( '/commission-report', array( 'startDate' => $start_date, 'endDate' => $end_date ) );

		if ( ! is_wp_error( $fresh_data ) && ! empty( $fresh_data ) ) {
			set_transient( $cache_key, $fresh_data, self::CACHE_DURATION_SECONDS );
		}

		return $fresh_data;
	}

	public function get_promotion_methods_report( string $start_date, string $end_date ) {
		$cache_key   = 'bol_report_promotion_' . md5( $start_date . '_' . $end_date );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$fresh_data = $this->make_request( '/promotion-report', array( 'startDate' => $start_date, 'endDate' => $end_date ) );

		if ( ! is_wp_error( $fresh_data ) && ! empty( $fresh_data ) ) {
			set_transient( $cache_key, $fresh_data, self::CACHE_DURATION_SECONDS );
		}

		return $fresh_data;
	}

	public function get_available_sites(): array {
		$end_date         = current_time( 'Y-m-d' );
		$start_date_obj   = date_create( $end_date, wp_timezone() );
		$start_date_obj->modify( '-89 days' );
		$start_date = $start_date_obj->format( 'Y-m-d' );

		$report_data = $this->get_promotion_methods_report( $start_date, $end_date );

		$sites = array();

		if ( is_wp_error( $report_data ) || ! isset( $report_data['items'] ) || empty( $report_data['items'] ) ) {
			return $sites;
		}

		foreach ( $report_data['items'] as $item ) {
			if ( isset( $item['siteCode'] ) && isset( $item['siteName'] ) ) {
				$sites[ sanitize_text_field( $item['siteCode'] ) ] = sanitize_text_field( $item['siteName'] );
			}
		}

		return $sites;
	}
}
