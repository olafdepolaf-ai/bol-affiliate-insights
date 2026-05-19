<?php

namespace TuinenBalkon\AffiliateLinkChecker\Service;

class TradeTrackerService {

	const WSDL = 'https://ws.tradetracker.com/soap/affiliate?wsdl';

	private $client = null;

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

			// authenticate() returns void — throws SoapFault on failure
			$this->client->authenticate(
				(int) $customer_id,
				$access_key,
				false,
				'nl_NL',
				false
			);
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

	/**
	 * Haalt rapport op voor elk afgelopen maand van het opgegeven jaar.
	 * Retourneert array geïndexeerd op maandnummer (1–12), null voor toekomstige maanden.
	 *
	 * @return array|\WP_Error  array[1..12] => ReportData object|null
	 */
	public function get_report_year( string $site_id, int $year ): array|\WP_Error {
		$cache_key = 'alc_tt_year_' . md5( $site_id . '_' . $year );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$current_year  = (int) gmdate( 'Y' );
		$current_month = (int) gmdate( 'n' );
		$max_month     = ( $year === $current_year ) ? $current_month : 12;

		$months = [];
		for ( $m = 1; $m <= 12; $m++ ) {
			if ( $m > $max_month ) {
				$months[ $m ] = null;
				continue;
			}

			$days_in_month = (int) gmdate( 't', mktime( 0, 0, 0, $m, 1, $year ) );
			$filter           = new \stdClass();
			$filter->dateFrom = sprintf( '%04d-%02d-01', $year, $m );
			$filter->dateTo   = sprintf( '%04d-%02d-%02d', $year, $m, $days_in_month );

			try {
				$months[ $m ] = $client->getReportAffiliateSite( $site_id, $filter );
			} catch ( \Exception $e ) {
				$months[ $m ] = null;
			}
		}

		// Voorbije jaren langer cachen
		$ttl = ( $year < $current_year ) ? 86400 : 3600;
		set_transient( $cache_key, $months, $ttl );

		return $months;
	}

	public function clear_cache(): void {
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
