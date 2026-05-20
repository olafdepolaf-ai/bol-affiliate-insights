<?php
namespace TuinenBalkon\BolAffiliateInsights\Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ApiAuthService {

    /**
     * Transient key used for caching the Bol.com API access token.
     * @const string
     */
    const ACCESS_TOKEN_TRANSIENT_KEY = 'bol_api_access_token';

    /**
     * Constructor for ApiAuthService.
     *
     * Currently, no specific actions are taken in the constructor.
     * Credentials (Client ID and Secret) are fetched from WordPress options
     * directly within the methods that require them.
     */
    public function __construct() {
        // Credentials will be fetched from options within methods like request_new_access_token().
    }

    /**
     * Retrieves an access token for the Bol.com API.
     *
     * This method first checks if a valid access token is stored in a WordPress transient.
     * If a valid token exists, it's returned. Otherwise, it calls `request_new_access_token()`
     * to fetch a new token from the API. The new token is then cached in a transient
     * for future use (with an expiry slightly less than the token's actual lifespan to ensure validity).
     *
     * @return string|\WP_Error The access token string on success, or a \WP_Error object on failure.
     */
    public function get_access_token() {
        // Attempt to retrieve the cached token data from the transient.
        $token_data = get_transient( self::ACCESS_TOKEN_TRANSIENT_KEY );

        // Check if valid cached token data exists.
        if ( false !== $token_data && isset( $token_data['access_token'] ) ) {
            return $token_data['access_token']; // Return the cached token.
        }

        // If no valid cached token, request a new one.
        $new_token_data = $this->request_new_access_token();

        // Handle errors from the token request.
        if ( is_wp_error( $new_token_data ) ) {
            return $new_token_data; // Propagate \WP_Error.
        }

        // If new token data is successfully retrieved, cache and return it.
        if ( isset( $new_token_data['access_token'] ) && isset( $new_token_data['expires_in'] ) ) {
            // Cache the new token, adjusting expiry time slightly for buffer (e.g., 30 seconds less).
            set_transient( self::ACCESS_TOKEN_TRANSIENT_KEY, $new_token_data, $new_token_data['expires_in'] - 30 );
            return $new_token_data['access_token']; // Return the new token.
        }

        // Fallback if token request was successful but data format is unexpected.
        return new \WP_Error('token_processing_error', 'Failed to process the new access token.');
    }

    /**
     * Requests a new access token from the Bol.com API.
     *
     * This private method handles the actual POST request to the Bol.com token endpoint.
     * It retrieves API credentials from WordPress options and uses them for Basic Authentication.
     *
     * @return array|\WP_Error An array containing 'access_token' and 'expires_in' on successful
     *                        token retrieval, or a \WP_Error object on failure.
     */
    private function request_new_access_token() {
        // Retrieve API credentials stored in WordPress options.
        $credentials = get_option( 'bol_affiliate_insights_credentials' );
        $client_id = isset( $credentials['client_id'] ) ? $credentials['client_id'] : '';
        $client_secret = isset( $credentials['client_secret'] ) ? $credentials['client_secret'] : '';

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new \WP_Error('missing_credentials', 'Client ID or Client Secret is not configured.');
        }

        $url = 'https://login.bol.com/token?grant_type=client_credentials';
        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
            ),
            'timeout' => 20, // seconds
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== $response_code ) {
            return new \WP_Error(
                'token_request_failed',
                'Failed to retrieve access token. Status: ' . $response_code,
                array( 'status' => $response_code, 'body' => $body )
            );
        }

        $data = json_decode( $body, true );

        if ( null === $data || ! isset( $data['access_token'] ) || ! isset( $data['expires_in'] ) ) {
            return new \WP_Error(
                'invalid_token_response',
                'Invalid token response from API.',
                array( 'body' => $body )
            );
        }

        return array(
            'access_token' => $data['access_token'],
            'expires_in'   => $data['expires_in'],
        );
    }
}