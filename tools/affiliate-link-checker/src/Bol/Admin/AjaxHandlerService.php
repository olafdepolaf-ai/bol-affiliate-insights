<?php

namespace TuinenBalkon\AffiliateLinkChecker\Bol\Admin;

use TuinenBalkon\AffiliateLinkChecker\Bol\Service\ApiAuthService;
use TuinenBalkon\AffiliateLinkChecker\Bol\Service\ApiClient;
use TuinenBalkon\AffiliateLinkChecker\Bol\Service\Logger;
use TuinenBalkon\AffiliateLinkChecker\Bol\Service\ReportDataService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AjaxHandlerService {

	private ReportDataService $report_data_service;
	private ApiAuthService    $api_auth_service;
	private ApiClient         $api_client;

	public function __construct( ReportDataService $report_data_service, ApiAuthService $api_auth_service, ApiClient $api_client ) {
		$this->report_data_service = $report_data_service;
		$this->api_auth_service    = $api_auth_service;
		$this->api_client          = $api_client;

		add_action( 'wp_ajax_bol_test_connection',      array( $this, 'handle_test_connection_ajax' ) );
		add_action( 'wp_ajax_bol_fetch_chart_data',     array( $this, 'handle_fetch_chart_data_ajax' ) );
		add_action( 'wp_ajax_bol_fetch_available_sites', array( $this, 'handle_fetch_available_sites_ajax' ) );
		add_action( 'wp_ajax_bol_clear_cache',          array( $this, 'handle_clear_cache_ajax' ) );
	}

	public function handle_test_connection_ajax(): void {
		check_ajax_referer( 'bol_test_connection_nonce', 'nonce' );
		$token_data = $this->api_auth_service->get_access_token();

		if ( is_wp_error( $token_data ) ) {
			wp_send_json_error( array(
				'message' => 'Connection Failed: ' . $token_data->get_error_message(),
				'code'    => $token_data->get_error_code(),
			) );
		} elseif ( $token_data ) {
			wp_send_json_success( array( 'message' => 'Connection Successful! Access token obtained.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Connection Failed: Unknown error retrieving access token.' ) );
		}
	}

	public function handle_fetch_chart_data_ajax(): void {
		check_ajax_referer( 'bol_fetch_chart_data_nonce', 'nonce' );
		$metric      = isset( $_POST['metric'] )      ? sanitize_key( $_POST['metric'] )      : 'orders';
		$period      = isset( $_POST['period'] )      ? sanitize_key( $_POST['period'] )      : 'last_4_weeks';
		$granularity = isset( $_POST['granularity'] ) ? sanitize_key( $_POST['granularity'] ) : 'auto';
		$site_filter = isset( $_POST['site'] )        ? sanitize_key( $_POST['site'] )        : 'all_sites';

		try {
			$response_data = $this->report_data_service->get_chart_data( $metric, $period, $granularity, $site_filter );
			wp_send_json_success( $response_data );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	public function handle_fetch_available_sites_ajax(): void {
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

	public function handle_clear_cache_ajax(): void {
		check_ajax_referer( 'bol_clear_cache_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_bol\_%'
			    OR option_name LIKE '_transient_timeout_bol\_%'"
		);

		Logger::debug( 'Cache cleared.', array( 'rows_deleted' => $deleted ) );

		wp_send_json_success( array(
			'message' => sprintf( 'Cache geleegd. %d item(s) verwijderd.', (int) $deleted ),
		) );
	}
}
