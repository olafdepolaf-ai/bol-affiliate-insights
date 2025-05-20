<?php
/**
 * Bol_API_Client Class
 *
 * Handles communication with the Bol.com Affiliate Reports API v2.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Bol_API_Client' ) ) {
    /**
     * Bol_API_Client Class
     *
     * Handles communication with the Bol.com Affiliate Reports API v2.
     * This class is responsible for making requests to specific API endpoints,
     * handling authentication via the Bol_API_Auth_Service, and managing
     * caching of API responses.
     */
    class Bol_API_Client {

        /**
         * Base URL for the Bol.com Affiliate Reports API v2.
         * @const string
         */
        const API_BASE_URL = 'https://api.bol.com/marketing/affiliate/reports/v2';
        
        /**
         * Default cache duration for API responses, in seconds.
         * Uses WordPress's HOUR_IN_SECONDS constant for clarity (1 hour).
         * @const int
         */
        const CACHE_DURATION_SECONDS = HOUR_IN_SECONDS;

        /**
         * Instance of Bol_API_Auth_Service.
         * Used to obtain access tokens for API requests.
         *
         * @var Bol_API_Auth_Service
         */
        private $auth_service;

        /**
         * Constructor for Bol_API_Client.
         *
         * Initializes the API client with an instance of the authentication service.
         *
         * @param Bol_API_Auth_Service $auth_service Instance of the Bol_API_Auth_Service.
         */
        public function __construct( Bol_API_Auth_Service $auth_service ) {
            $this->auth_service = $auth_service;
        }

        /**
         * Makes a generic request to the Bol.com API.
         *
         * This private method handles the core logic of making an API request,
         * including retrieving an access token, constructing the request URL and arguments,
         * executing the request using `wp_remote_request`, and handling potential errors.
         *
         * @param string $endpoint The API endpoint path (e.g., '/order-report').
         * @param array  $args     Optional. Query parameters for GET requests or the request body for POST/PUT requests.
         * @param string $method   Optional. The HTTP method to use (e.g., 'GET', 'POST'). Default 'GET'.
         * @return array|WP_Error Decoded JSON response as an associative array on success, or a WP_Error object on failure.
         */
        private function make_request( $endpoint, $args = array(), $method = 'GET' ) {
            // Retrieve an access token.
            $token = $this->auth_service->get_access_token();

            // Handle token retrieval errors.
            if ( is_wp_error( $token ) ) {
                return $token; // Propagate the WP_Error.
            }
            if ( ! $token ) {
                // If token is false or empty for some reason.
                return new WP_Error('token_unavailable', 'Could not retrieve a valid access token.');
            }

            // Construct the full API request URL.
            $url = self::API_BASE_URL . $endpoint;

            // Prepare default request arguments.
            $request_args = array(
                'method'  => $method,
                'headers' => array(
                    'Accept'        => 'application/json', // Expect JSON response.
                    'Authorization' => 'Bearer ' . $token,  // Use Bearer token for authentication.
                ),
                'timeout' => 30, // Set request timeout in seconds.
            );

            // Handle query parameters for GET requests or body for POST/PUT requests.
            if ( ! empty( $args ) ) {
                if ( 'GET' === $method ) {
                    $url = add_query_arg( $args, $url ); // Add query parameters to URL.
                } else {
                    // For POST/PUT, $args is assumed to be the request body.
                    $request_args['body'] = json_encode( $args ); // Encode body as JSON.
                    $request_args['headers']['Content-Type'] = 'application/json'; // Set Content-Type header.
                }
            }

            // Execute the API request.
            $response = wp_remote_request( $url, $request_args );

            // Handle WordPress HTTP API errors.
            if ( is_wp_error( $response ) ) {
                return $response; // Propagate the WP_Error.
            }

            // Retrieve response code and body.
            $response_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            // Handle API-level errors (non-200 status codes).
            if ( 200 !== $response_code ) {
                return new WP_Error(
                    'api_request_failed',
                    'API request failed for ' . $endpoint . '. Status: ' . $response_code,
                    array( 'status' => $response_code, 'body' => $body )
                );
            }

            // Decode the JSON response body.
            $data = json_decode( $body, true );

            // Handle JSON decoding errors.
            if ( null === $data ) {
                return new WP_Error(
                    'invalid_api_response',
                    'Invalid JSON response from API for ' . $endpoint,
                    array( 'body' => $body )
                );
            }

            return $data; // Return the decoded API response data.
        }

        /**
         * Retrieves the orders report from the Bol.com API.
         *
         * Fetches order data for a specified date range. Results are cached using
         * WordPress transients to improve performance and reduce API calls.
         *
         * @param string $start_date Start date in YYYY-MM-DD format.
         * @param string $end_date   End date in YYYY-MM-DD format.
         * @return array|WP_Error Decoded API response as an array on success, or a WP_Error object on failure.
         */
        public function get_orders_report( $start_date, $end_date ) {
            // Generate a unique cache key based on the report type and date range.
            $cache_key = 'bol_report_orders_' . md5( $start_date . '_' . $end_date );
            // Try to retrieve cached data.
            $cached_data = get_transient( $cache_key );

            if ( false !== $cached_data ) {
                return $cached_data; // Return cached data if available and valid.
            }

            // If no cache, fetch fresh data from the API.
            $fresh_data = $this->make_request( '/order-report', array( 'startDate' => $start_date, 'endDate' => $end_date ) );

            // Cache the fresh data if the request was successful and data is not empty.
            if ( ! is_wp_error( $fresh_data ) && ! empty( $fresh_data ) ) {
                set_transient( $cache_key, $fresh_data, self::CACHE_DURATION_SECONDS );
            }

            return $fresh_data; // Return fresh data (or error if request failed).
        }

        /**
         * Retrieves the commission and revenue report from the Bol.com API.
         *
         * Fetches commission and revenue data for a specified date range.
         * Results are cached using WordPress transients.
         *
         * @param string $start_date Start date in YYYY-MM-DD format.
         * @param string $end_date   End date in YYYY-MM-DD format.
         * @return array|WP_Error Decoded API response as an array on success, or a WP_Error object on failure.
         */
        public function get_commission_revenue_report( $start_date, $end_date ) {
            $cache_key = 'bol_report_commission_' . md5( $start_date . '_' . $end_date );
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

        /**
         * Retrieves the promotion methods report from the Bol.com API.
         *
         * Fetches data on promotion methods (e.g., clicks, impressions, revenue per promotion)
         * for a specified date range. Results are cached using WordPress transients.
         *
         * @param string $start_date Start date in YYYY-MM-DD format.
         * @param string $end_date   End date in YYYY-MM-DD format.
         * @return array|WP_Error Decoded API response as an array on success, or a WP_Error object on failure.
         */
        public function get_promotion_methods_report( $start_date, $end_date ) {
            $cache_key = 'bol_report_promotion_' . md5( $start_date . '_' . $end_date );
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

        /**
         * Retrieves a list of available affiliate sites based on recent promotion method reports.
         *
         * This method fetches promotion data over a defined period (e.g., last 90 days)
         * to identify unique sites (siteCode and siteName) that have recorded activity.
         * This list can be used to populate site selection dropdowns in the UI.
         * The underlying `get_promotion_methods_report` call benefits from its own caching.
         *
         * @return array An associative array of [site_code => site_name], or an empty array on failure or if no sites are found.
         */
        public function get_available_sites() {
            // Define the period to scan for site activity.
            $end_date = current_time('Y-m-d');
            $start_date_obj = date_create($end_date, wp_timezone());
            $start_date_obj->modify('-89 days'); // Approx. 90 days period.
            $start_date = $start_date_obj->format('Y-m-d');

            // Fetch promotion report data, which includes site information.
            // This call utilizes the caching implemented in get_promotion_methods_report().
            $report_data = $this->get_promotion_methods_report( $start_date, $end_date );

            $sites = array(); // Initialize an empty array for sites.

            // Handle errors or empty responses from the API call.
            if ( is_wp_error( $report_data ) || !isset( $report_data['items'] ) || empty( $report_data['items'] ) ) {
                // Optionally log error for debugging.
                // error_log('BOL API Client: Failed to fetch sites or no sites found in period for get_available_sites. Error: ' . (is_wp_error($report_data) ? $report_data->get_error_message() : 'No items'));
                return $sites; // Return empty array if error or no data.
            }

            // Process the report items to extract unique site codes and names.
            foreach ( $report_data['items'] as $item ) {
                if ( isset( $item['siteCode'] ) && isset( $item['siteName'] ) ) {
                    // Sanitize and store site information, using siteCode as key for uniqueness.
                    $sites[ sanitize_text_field($item['siteCode']) ] = sanitize_text_field($item['siteName']);
                }
            }

            return $sites; // Return the array of unique sites.
        }
    }
}
?>
