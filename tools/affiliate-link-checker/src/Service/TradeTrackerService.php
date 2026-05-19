<?php

namespace TuinenBalkon\AffiliateLinkChecker\Service;

class TradeTrackerService {

	const WSDL = 'https://ws.tradetracker.com/soap/affiliate?wsdl';

	private ?\SoapClient $client = null;

	private function get_client(): \SoapClient|\WP_Error {
		if ( $this->client ) {
			return $this->client;
		}

		if ( ! class_exists( 'SoapClient' ) ) {
			return new \WP_Error( 'no_soap', 'PHP SOAP extensie is niet beschikbaar op deze server.' );
		}

		$customer_id = get_option( 'alc_tt_customer_id', '' );
		$access_key  = get_option( 'alc_tt_access_key', '' );

		if ( empty( $customer_id ) || empty( $access_key ) ) {
			return new \WP_Error( 'no_credentials', 'Vul eerst de TradeTracker inloggegevens in.' );
		}

		try {
			$this->client = new \SoapClient( self::WSDL, [
				'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
				'cache_wsdl'  => WSDL_CACHE_BOTH,
				'trace'       => false,
			] );

			$authenticated = $this->client->authenticate(
				(int) $customer_id,
				$access_key,
				false,
				'nl_NL',
				false
			);

			if ( ! $authenticated ) {
				$this->client = null;
				return new \WP_Error( 'auth_failed', 'Authenticatie mislukt. Controleer klant-ID en toegangssleutel.' );
			}
		} catch ( \Exception $e ) {
			$this->client = null;
			return new \WP_Error( 'soap_error', 'SOAP fout: ' . $e->getMessage() );
		}

		return $this->client;
	}

	public function get_affiliate_sites(): array|\WP_Error {
		$cached = get_transient( 'alc_tt_sites' );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		try {
			$result = $client->getAffiliateSites();
			$sites  = $this->to_array( $result );
			set_transient( 'alc_tt_sites', $sites, 15 * MINUTE_IN_SECONDS );
			return $sites;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	public function get_campaigns( string $site_id ): array|\WP_Error {
		$cache_key = 'alc_tt_campaigns_' . md5( $site_id );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		try {
			$filter                   = new \stdClass();
			$filter->assignmentStatus = 'accepted';
			$result                   = $client->getCampaigns( $site_id, $filter );
			$campaigns                = $this->to_array( $result );
			set_transient( $cache_key, $campaigns, 15 * MINUTE_IN_SECONDS );
			return $campaigns;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	public function get_report_last_month( string $site_id ): object|array|\WP_Error {
		$cache_key = 'alc_tt_report_' . md5( $site_id );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$start = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
		$end   = gmdate( 'Y-m-t', strtotime( 'last day of last month' ) );

		try {
			$result = $client->getReportAffiliateSite( $site_id, $start, $end );
			set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
			return $result;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	public function clear_cache(): void {
		delete_transient( 'alc_tt_sites' );
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_alc_tt_%'
			    OR option_name LIKE '_transient_timeout_alc_tt_%'"
		);
	}

	private function to_array( mixed $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_object( $value ) ) {
			return (array) $value;
		}
		return [];
	}
}
