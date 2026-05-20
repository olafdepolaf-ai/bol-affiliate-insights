<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Admin;

use TuinenBalkon\TBMoneyManager\Bol\Service\ApiAuthService;
use TuinenBalkon\TBMoneyManager\Bol\Service\ApiClient;
use TuinenBalkon\TBMoneyManager\Bol\Service\Logger;
use TuinenBalkon\TBMoneyManager\Bol\Service\ReportDataService;

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

		add_action( 'wp_ajax_bol_test_connection',                    array( $this, 'handle_test_connection_ajax' ) );
		add_action( 'wp_ajax_tbmm_bol_test_marketing_connection',    array( $this, 'handle_test_marketing_connection_ajax' ) );
		add_action( 'wp_ajax_bol_fetch_chart_data',      array( $this, 'handle_fetch_chart_data_ajax' ) );
		add_action( 'wp_ajax_bol_fetch_available_sites', array( $this, 'handle_fetch_available_sites_ajax' ) );
		add_action( 'wp_ajax_bol_clear_cache',           array( $this, 'handle_clear_cache_ajax' ) );
		add_action( 'wp_ajax_tbmm_bol_export_csv',       array( $this, 'handle_export_csv_ajax' ) );
	}

	public function handle_test_connection_ajax(): void {
		check_ajax_referer( 'bol_test_connection_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}
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

	public function handle_test_marketing_connection_ajax(): void {
		check_ajax_referer( 'tbmm_bol_marketing_test_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$credentials   = get_option( 'tbmm_bol_marketing_credentials', array() );
		$client_id     = isset( $credentials['client_id'] )     ? trim( $credentials['client_id'] )     : '';
		$client_secret = isset( $credentials['client_secret'] ) ? trim( $credentials['client_secret'] ) : '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			wp_send_json_error( array( 'message' => 'Marketing API credentials zijn niet ingesteld. Vul Client ID en Client Secret in via de instellingen.' ) );
			return;
		}

		$response = wp_remote_post(
			'https://login.bol.com/token?grant_type=client_credentials',
			array(
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Verbindingsfout: ' . $response->get_error_message() ) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && isset( $body['access_token'] ) ) {
			wp_send_json_success( array( 'message' => 'Verbinding geslaagd! Access token ontvangen van Marketing Catalog API.' ) );
		} else {
			$detail = isset( $body['error_description'] ) ? $body['error_description'] : "HTTP $code";
			wp_send_json_error( array( 'message' => 'Verbinding mislukt: ' . $detail ) );
		}
	}

	public function handle_fetch_chart_data_ajax(): void {
		check_ajax_referer( 'bol_fetch_chart_data_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}
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

	public function handle_export_csv_ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Geen toegang.', 403 );
		}

		check_ajax_referer( 'tbmm_bol_csv_export', '_nonce' );

		$type       = isset( $_GET['type'] )  ? sanitize_key( $_GET['type'] )                : '';
		$start_date = isset( $_GET['start'] ) ? sanitize_text_field( $_GET['start'] )        : '';
		$end_date   = isset( $_GET['end'] )   ? sanitize_text_field( $_GET['end'] )          : '';

		if ( ! $type || ! $start_date || ! $end_date ) {
			wp_die( 'Ongeldige parameters.', 400 );
		}

		$allowed_types = array( 'orders', 'commission-revenue', 'promotion-methods' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			wp_die( 'Onbekend type.', 400 );
		}

		switch ( $type ) {
			case 'orders':
				$response = $this->api_client->get_orders_report( $start_date, $end_date );
				$columns  = array(
					'orderDate'    => 'Datum',
					'orderId'      => 'Order ID',
					'orderItemId'  => 'Order Item ID',
					'productTitle' => 'Product',
					'quantity'     => 'Aantal',
					'priceInclVat' => 'Prijs incl. BTW',
					'commission'   => 'Commissie',
					'status'       => 'Status',
				);
				break;

			case 'commission-revenue':
				$response = $this->api_client->get_commission_revenue_report( $start_date, $end_date );
				$columns  = array(
					'orderDate'              => 'Datum',
					'siteName'               => 'Site',
					'frameType'              => 'Frame Type',
					'name'                   => 'Link naam',
					'subId'                  => 'SubID',
					'commissionPercentage'   => 'Commissie %',
					'commissionOriginal'     => 'Commissie origineel',
					'commissionApproved'     => 'Commissie goedgekeurd',
					'commissionOpen'         => 'Commissie open',
					'revenueOriginalInclVat' => 'Omzet origineel incl. BTW',
					'revenueApprovedInclVat' => 'Omzet goedgekeurd incl. BTW',
					'quantityPayable'        => 'Aantal betaalbaar',
				);
				break;

			case 'promotion-methods':
				$response = $this->api_client->get_promotion_methods_report( $start_date, $end_date );
				$columns  = array(
					'date'              => 'Datum',
					'siteName'          => 'Site',
					'name'              => 'Link naam',
					'subId'             => 'SubID',
					'clicks'            => 'Kliks',
					'impressions'       => 'Impressies',
					'clickThroughRate'  => 'CTR (%)',
					'earningsPerClick'  => 'EPC (€)',
					'orders'            => 'Orders',
					'conversion'        => 'Conversie (%)',
					'revenueInclVat'    => 'Omzet incl. BTW (€)',
					'averageOrderValue' => 'Gem. orderwaarde (€)',
				);
				break;
		}

		if ( is_wp_error( $response ) ) {
			wp_die( 'API-fout: ' . esc_html( $response->get_error_message() ), 500 );
		}

		if ( ! isset( $response['items'] ) || ! is_array( $response['items'] ) ) {
			wp_die( 'Geen data ontvangen van Bol.com API.', 500 );
		}

		$filename = $type . '_' . $start_date . '_' . $end_date . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );

		// UTF-8 BOM zodat Excel correct opent
		fputs( $out, "\xEF\xBB\xBF" );

		// Headerrij
		fputcsv( $out, array_values( $columns ), ';' );

		foreach ( $response['items'] as $item ) {
			$row = array();
			foreach ( array_keys( $columns ) as $key ) {
				$row[] = $item[ $key ] ?? '';
			}
			fputcsv( $out, $row, ';' );
		}

		fclose( $out );
		exit;
	}
}
