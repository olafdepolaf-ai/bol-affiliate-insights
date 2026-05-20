<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiAuthService {

	const ACCESS_TOKEN_TRANSIENT_KEY = 'bol_api_access_token';

	public function get_access_token() {
		$token_data = get_transient( self::ACCESS_TOKEN_TRANSIENT_KEY );

		if ( false !== $token_data && isset( $token_data['access_token'] ) ) {
			return $token_data['access_token'];
		}

		$new_token_data = $this->request_new_access_token();

		if ( is_wp_error( $new_token_data ) ) {
			return $new_token_data;
		}

		if ( isset( $new_token_data['access_token'] ) && isset( $new_token_data['expires_in'] ) ) {
			set_transient( self::ACCESS_TOKEN_TRANSIENT_KEY, $new_token_data, $new_token_data['expires_in'] - 30 );
			return $new_token_data['access_token'];
		}

		return new \WP_Error( 'token_processing_error', 'Failed to process the new access token.' );
	}

	private function request_new_access_token() {
		$credentials   = get_option( 'bol_affiliate_insights_credentials' );
		$client_id     = isset( $credentials['client_id'] ) ? $credentials['client_id'] : '';
		$client_secret = isset( $credentials['client_secret'] ) ? $credentials['client_secret'] : '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new \WP_Error( 'missing_credentials', 'Client ID or Client Secret is not configured.' );
		}

		$url      = 'https://login.bol.com/token?grant_type=client_credentials';
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'token_request_failed',
				'Failed to retrieve access token. Status: ' . $response_code,
				array( 'status' => $response_code, 'body' => $body )
			);
		}

		$data = json_decode( $body, true );

		if ( null === $data || ! isset( $data['access_token'] ) || ! isset( $data['expires_in'] ) ) {
			return new \WP_Error( 'invalid_token_response', 'Invalid token response from API.', array( 'body' => $body ) );
		}

		return array(
			'access_token' => $data['access_token'],
			'expires_in'   => $data['expires_in'],
		);
	}
}
