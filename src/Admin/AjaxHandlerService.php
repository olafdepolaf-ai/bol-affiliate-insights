<?php
namespace TuinenBalkon\BolAffiliateInsights\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class AjaxHandlerService {
    private $report_data_service;
    private $api_auth_service;
    private $api_client;

    public function __construct(\TuinenBalkon\BolAffiliateInsights\Service\ReportDataService $report_data_service, \TuinenBalkon\BolAffiliateInsights\Service\ApiAuthService $api_auth_service, \TuinenBalkon\BolAffiliateInsights\Service\ApiClient $api_client) {
        $this->report_data_service = $report_data_service;
        $this->api_auth_service = $api_auth_service;
        $this->api_client = $api_client;
        add_action( 'wp_ajax_bol_test_connection', array( $this, 'handle_test_connection_ajax' ) );
        add_action( 'wp_ajax_bol_fetch_chart_data', array( $this, 'handle_fetch_chart_data_ajax' ) );
        add_action( 'wp_ajax_bol_fetch_available_sites', array( $this, 'handle_fetch_available_sites_ajax' ) );
    }

    public function handle_test_connection_ajax() {
        check_ajax_referer( 'bol_test_connection_nonce', 'nonce' );
        $token_data = $this->api_auth_service->get_access_token();
        if ( is_wp_error( $token_data ) ) {
            wp_send_json_error( array(
                'message' => 'Connection Failed: ' . $token_data->get_error_message(),
                'code' => $token_data->get_error_code()
            ) );
        } elseif ( $token_data ) {
            wp_send_json_success( array( 'message' => 'Connection Successful! Access token obtained.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Connection Failed: Unknown error retrieving access token.' ) );
        }
    }

    public function handle_fetch_chart_data_ajax() {
        // check_ajax_referer( 'bol_fetch_chart_data_nonce', 'nonce' );
        $metric = isset( $_POST['metric'] ) ? sanitize_key( $_POST['metric'] ) : 'orders';
        $period = isset( $_POST['period'] ) ? sanitize_key( $_POST['period'] ) : 'last_4_weeks';
        $granularity = isset( $_POST['granularity'] ) ? sanitize_key( $_POST['granularity'] ) : 'auto';
        $site_filter = isset( $_POST['site'] ) ? sanitize_key( $_POST['site'] ) : 'all_sites';

        try {
            $response_data = $this->report_data_service->get_chart_data($metric, $period, $granularity, $site_filter);
            wp_send_json_success($response_data);
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function handle_fetch_available_sites_ajax() {
        check_ajax_referer( 'bol_fetch_sites_nonce', 'nonce' );
        $sites = $this->api_client->get_available_sites();
        if ( is_wp_error( $sites ) ) {
            wp_send_json_error( array( 'message' => 'Error fetching sites: ' . $sites->get_error_message() ) );
        } elseif ( empty( $sites ) ) {
            wp_send_json_success( array( 'sites' => array(), 'message' => 'No sites found or API access issue for sites.' ) );
        } else {
            wp_send_json_success( array( 'sites' => $sites ) );
        }
    }
}
