<?php

namespace TuinenBalkon\AffiliateLinkChecker\Service;

class TradeTrackerService {

	const WSDL = 'https://ws.tradetracker.com/soap/affiliate?wsdl';

	// Versioned prefix — bump to v2, v3, etc. to bust all caches on breaking changes
	const CACHE_PREFIX = 'alc_tt_v1_';

	// TTL constants as integer literals (cannot use WP define() constants in class const)
	const TTL_SITES        = 21600;  // 6h  — sites list rarely changes
	const TTL_CAMPAIGNS    = 3600;   // 1h
	const TTL_REPORT       = 3600;   // 1h  — current year
	const TTL_REPORT_PAST  = 86400;  // 24h — past years
	const TTL_SALES        = 3600;   // 1h  — current year
	const TTL_SALES_PAST   = 86400;  // 24h — past years
	const TTL_CLICKS       = 3600;   // 1h  — current year
	const TTL_CLICKS_PAST  = 86400;  // 24h — past years
	const TTL_MATERIALS    = 21600;  // 6h  — text link materials rarely change

	private $client = null;

	// -------------------------------------------------------------------------
	// Cache helpers
	// -------------------------------------------------------------------------

	private function cache_get( string $key ): mixed {
		$cached = get_transient( self::CACHE_PREFIX . $key );
		if ( false === $cached ) {
			return false;
		}
		// Guard against stale scalar values from older cache versions
		if ( ! is_array( $cached ) && ! is_object( $cached ) ) {
			return false;
		}
		return $cached;
	}

	private function cache_set( string $key, mixed $value, int $ttl ): void {
		// Never cache error states
		if ( is_wp_error( $value ) ) {
			return;
		}
		set_transient( self::CACHE_PREFIX . $key, $value, $ttl );
	}

	private function cache_delete( string $key ): void {
		delete_transient( self::CACHE_PREFIX . $key );
	}

	// -------------------------------------------------------------------------
	// SOAP client
	// -------------------------------------------------------------------------

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

	// -------------------------------------------------------------------------
	// Public API methods
	// -------------------------------------------------------------------------

	public function get_affiliate_sites(): array|\WP_Error {
		$cache_key = 'sites';
		$cached    = $this->cache_get( $cache_key );
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
			$this->cache_set( $cache_key, $sites, self::TTL_SITES );
			return $sites;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	public function get_campaigns( string $site_id ): array|\WP_Error {
		$cache_key = 'campaigns_' . md5( $site_id );
		$cached    = $this->cache_get( $cache_key );
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
			$this->cache_set( $cache_key, $campaigns, self::TTL_CAMPAIGNS );
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
		$cache_key = 'year_' . md5( $site_id . '_' . $year );
		$cached    = $this->cache_get( $cache_key );
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

		$ttl = ( $year < $current_year ) ? self::TTL_REPORT_PAST : self::TTL_REPORT;
		$this->cache_set( $cache_key, $months, $ttl );

		return $months;
	}

	/**
	 * Haalt alle sales (conversie-transacties) op voor het opgegeven jaar.
	 * Gesorteerd op registratiedatum aflopend, max 500 per jaar.
	 */
	public function get_sales_year( string $site_id, int $year ): array|\WP_Error {
		$cache_key = 'sales_' . md5( $site_id . '_' . $year );
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$filter                        = new \stdClass();
		$filter->transactionType       = 'sale';
		$filter->registrationDateFrom  = sprintf( '%04d-01-01T00:00:00', $year );
		$filter->registrationDateTo    = sprintf( '%04d-12-31T23:59:59', $year );
		$filter->limit                 = 500;
		$filter->sort                  = 'registrationDate';
		$filter->sortDirection         = 'descending';

		try {
			$result       = $client->getConversionTransactions( $site_id, $filter );
			$transactions = $this->to_array( $result );
			$ttl          = ( $year < (int) gmdate( 'Y' ) ) ? self::TTL_SALES_PAST : self::TTL_SALES;
			$this->cache_set( $cache_key, $transactions, $ttl );
			return $transactions;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	/**
	 * Haalt alle klik-transacties op voor het opgegeven jaar.
	 * Gesorteerd op registratiedatum aflopend, max 1000 per jaar.
	 */
	public function get_clicks_year( string $site_id, int $year ): array|\WP_Error {
		$cache_key = 'clicks_' . md5( $site_id . '_' . $year );
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$filter                        = new \stdClass();
		$filter->registrationDateFrom  = sprintf( '%04d-01-01T00:00:00', $year );
		$filter->registrationDateTo    = sprintf( '%04d-12-31T23:59:59', $year );
		$filter->limit                 = 1000;
		$filter->sort                  = 'registrationDate';
		$filter->sortDirection         = 'descending';

		try {
			$result = $client->getClickTransactions( $site_id, $filter );
			$clicks = $this->to_array( $result );
			$ttl    = ( $year < (int) gmdate( 'Y' ) ) ? self::TTL_CLICKS_PAST : self::TTL_CLICKS;
			$this->cache_set( $cache_key, $clicks, $ttl );
			return $clicks;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	/**
	 * Haalt tekstlink-materialen op voor alle campagnes van de affiliate site.
	 * Geeft een map terug: campaignID (string) => base tracking URL (string).
	 * De base URL eindigt altijd met een lege referentie-slot (bv. "?tt=8892_12_98344_")
	 * zodat de referentie er direct achter geplakt kan worden.
	 *
	 * @return array<string,string>|\WP_Error
	 */
	public function get_text_material_urls( string $site_id ): array|\WP_Error {
		$cache_key = 'text_materials_' . md5( $site_id );
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		try {
			// getMaterialTextItems( affiliateSiteID, materialOutputType, MaterialItemFilter )
			// materialOutputType null = alle formaten; lege filter = alle campagnes
			$filter  = new \stdClass();
			$result  = $client->getMaterialTextItems( $site_id, null, $filter );
			$items   = $this->to_array( $result );

			// Bouw map campagnID => base tracking URL (eerste material per campagne)
			$map = [];
			foreach ( $items as $item ) {
				$m           = (object) $item;
				$campaign_id = (string) ( is_object( $m->campaign ?? null ) ? ( $m->campaign->ID ?? '' ) : '' );
				if ( $campaign_id === '' || isset( $map[ $campaign_id ] ) ) {
					continue;
				}

				// Probeer URL direct, anders href uit HTML code extraheren
				$url = '';
				if ( ! empty( $m->URL ) ) {
					$url = (string) $m->URL;
				} elseif ( ! empty( $m->code ) ) {
					preg_match( '/href=["\']([^"\']+)["\']/i', (string) $m->code, $matches );
					$url = $matches[1] ?? '';
				}

				if ( $url === '' ) {
					continue;
				}

				// Normaliseer: zorg dat het tt-param eindigt met lege referentie (trailing _)
				// Bv. "?tt=8892_12_98344_bestaanderef" → "?tt=8892_12_98344_"
				$url = (string) preg_replace( '/([?&]tt=\d+_\d+_\d+_)[^&]*/', '$1', $url );

				$map[ $campaign_id ] = $url;
			}

			$this->cache_set( $cache_key, $map, self::TTL_MATERIALS );
			return $map;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	public function clear_cache(): void {
		global $wpdb;
		$prefix = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$prefix . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%'
			)
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

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
