<?php

namespace TuinenBalkon\TBMoneyManager\Service;

class TradeTrackerService {

	const WSDL = 'https://ws.tradetracker.com/soap/affiliate?wsdl';

	// Versioned prefix — bump to v2, v3, etc. to bust all caches on breaking changes
	const CACHE_PREFIX = 'tbmm_tt_v1_';

	// TTL constants as integer literals (cannot use WP define() constants in class const)
	const TTL_SITES         = 21600;  // 6h  — sites list rarely changes
	const TTL_CAMPAIGNS     = 3600;   // 1h
	const TTL_REPORT        = 3600;   // 1h  — current year
	const TTL_REPORT_PAST   = 86400;  // 24h — past years
	const TTL_SALES         = 3600;   // 1h  — current year
	const TTL_SALES_PAST    = 86400;  // 24h — past years
	const TTL_CLICKS        = 86400;  // 24h — current year
	const TTL_CLICKS_PAST   = 86400;  // 24h — past years
	const TTL_MATERIALS     = 21600;  // 6h  — text link materials rarely change
	const TTL_FEEDS         = 86400;  // 24h — feed-catalogus verandert hooguit dagelijks
	const TTL_FEED_PRODUCTS = 86400;  // 24h — productcatalogus verandert niet intraday
	const TTL_PENDING       = 3600;   // 1h
	const TTL_PAYMENTS      = 3600;   // 1h

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

		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );

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
		set_transient( self::CACHE_PREFIX . 'report_fetched_at_' . md5( $site_id . '_' . $year ), time(), $ttl );

		return $months;
	}

	public function get_report_fetched_at( string $site_id, int $year ): ?int {
		$ts = get_transient( self::CACHE_PREFIX . 'report_fetched_at_' . md5( $site_id . '_' . $year ) );
		return ( false !== $ts ) ? (int) $ts : null;
	}

	public function clear_report_cache( string $site_id, int $year ): void {
		$hash = md5( $site_id . '_' . $year );
		$this->cache_delete( 'year_' . $hash );
		delete_transient( self::CACHE_PREFIX . 'report_fetched_at_' . $hash );
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
			set_transient( self::CACHE_PREFIX . 'clicks_fetched_at_' . md5( $site_id . '_' . $year ), time(), $ttl );
			return $clicks;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	public function get_clicks_fetched_at( string $site_id, int $year ): ?int {
		$ts = get_transient( self::CACHE_PREFIX . 'clicks_fetched_at_' . md5( $site_id . '_' . $year ) );
		return ( false !== $ts ) ? (int) $ts : null;
	}

	public function clear_clicks_cache( string $site_id, int $year ): void {
		$hash = md5( $site_id . '_' . $year );
		$this->cache_delete( 'clicks_' . $hash );
		delete_transient( self::CACHE_PREFIX . 'clicks_fetched_at_' . $hash );
	}

	/**
	 * Haalt tekstlink-materialen op voor alle campagnes van de affiliate site.
	 * Geeft een map terug: campaignID (string) => base tracking URL (string).
	 * De base URL eindigt met een lege referentie-slot (bv. "?tt=8892_12_98344_")
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
			$filter = new \stdClass();
			// 'html' is the correct materialOutputType for text link materials.
			// null caused silent SOAP failures on some servers.
			$raw = $client->getMaterialTextItems( $site_id, 'html', $filter );

			// SOAP returns a single object (not array) when there is exactly one result.
			// to_array() would flatten that object into its properties, losing the item structure.
			if ( is_object( $raw ) ) {
				$items = [ $raw ];
			} elseif ( is_array( $raw ) ) {
				$items = $raw;
			} else {
				$items = [];
			}

			$map = [];
			foreach ( $items as $item ) {
				$m = is_object( $item ) ? $item : (object) $item;

				// Campaign kan een object of gecast array zijn
				$campaign = $m->campaign ?? null;
				if ( is_array( $campaign ) ) {
					$campaign = (object) $campaign;
				}
				$campaign_id = (string) ( is_object( $campaign ) ? ( $campaign->ID ?? '' ) : '' );

				if ( $campaign_id === '' || isset( $map[ $campaign_id ] ) ) {
					continue;
				}

				// URL ophalen: probeer meerdere property-namen, daarna href uit HTML-code
				$url = '';
				foreach ( [ 'URL', 'url', 'trackingURL', 'trackingUrl' ] as $prop ) {
					if ( ! empty( $m->$prop ) ) {
						$url = (string) $m->$prop;
						break;
					}
				}
				if ( $url === '' && ! empty( $m->code ) ) {
					// HTML-entities decoderen voor regex (bv. &amp; → &)
					$decoded = html_entity_decode( (string) $m->code, ENT_QUOTES | ENT_HTML5 );
					preg_match( '/href=["\']([^"\']+)["\']/i', $decoded, $matches );
					$url = $matches[1] ?? '';
				}

				if ( $url === '' ) {
					continue;
				}

				// Normaliseer: referentie-slot leeg maken (trailing _)
				// "?tt=8892_12_98344_bestaanderef" → "?tt=8892_12_98344_"
				$url = (string) preg_replace( '/([?&]tt=\d+_\d+_\d+_)[^&]*/', '$1', $url );

				$map[ $campaign_id ] = $url;
			}

			$this->cache_set( $cache_key, $map, self::TTL_MATERIALS );
			return $map;
		} catch ( \Exception $e ) {
			// Reset client: a failed getMaterialTextItems can invalidate the server-side
			// SOAP session, which would cause subsequent calls to fail with auth errors.
			$this->client = null;
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	/**
	 * Geeft het ID van de primaire affiliate site terug.
	 */
	public function get_primary_site_id(): string|\WP_Error {
		$sites = $this->get_affiliate_sites();
		if ( is_wp_error( $sites ) ) {
			return $sites;
		}
		if ( empty( $sites ) ) {
			return new \WP_Error( 'no_sites', 'Geen affiliate sites gevonden.' );
		}
		$primary = reset( $sites );
		return (string) ( is_object( $primary ) ? $primary->ID : ( $primary['ID'] ?? '' ) );
	}

	/**
	 * Haalt productfeeds op voor de opgegeven affiliate site.
	 * assignment_status: 'accepted' = alleen geaccepteerde feeds, '' = alle feeds.
	 */
	public function get_feeds( string $site_id, string $assignment_status = 'accepted' ): array|\WP_Error {
		$cache_key = 'feeds_' . md5( $site_id . '_' . $assignment_status );
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		try {
			$filter = new \stdClass();
			if ( $assignment_status !== '' ) {
				$filter->assignmentStatus = $assignment_status;
			}

			$raw = $client->getFeeds( (int) $site_id, $filter );

			if ( is_object( $raw ) ) {
				$feeds = [ $raw ];
			} elseif ( is_array( $raw ) ) {
				$feeds = $raw;
			} else {
				$feeds = [];
			}

			$this->cache_set( $cache_key, $feeds, self::TTL_FEEDS );
			return $feeds;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	/**
	 * Zoekt producten binnen een specifieke productfeed.
	 */
	public function get_feed_products( string $site_id, int $feed_id, string $search = '', int $limit = 25, int $offset = 0 ): array|\WP_Error {
		$cache_key = 'fp_' . md5( $site_id . '_' . $feed_id . '_' . $search . '_' . $limit . '_' . $offset );
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		try {
			$filter         = new \stdClass();
			$filter->feedID = $feed_id;
			$filter->limit  = $limit;
			$filter->offset = $offset;
			if ( $search !== '' ) {
				$filter->query = $search;
			}

			$raw = $client->getFeedProducts( (int) $site_id, $filter );

			if ( is_object( $raw ) ) {
				$products = [ $raw ];
			} elseif ( is_array( $raw ) ) {
				$products = $raw;
			} else {
				$products = [];
			}

			$this->cache_set( $cache_key, $products, self::TTL_FEED_PRODUCTS );
			return $products;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	/**
	 * Geeft de totale openstaande (pending) commissie terug.
	 * Kijkt maximaal 365 dagen terug voor transacties met status 'pending'.
	 *
	 * @return array{ commission: float, count: int }|\WP_Error
	 */
	public function get_pending_commission( string $site_id ) {
		$cache_key = 'pending_' . md5( $site_id );
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$filter                        = new \stdClass();
		$filter->transactionStatus     = 'pending';
		$filter->registrationDateFrom  = gmdate( 'Y-01-01T00:00:00', strtotime( '-1 year' ) );
		$filter->registrationDateTo    = gmdate( 'Y-m-d\T23:59:59' );
		$filter->limit                 = 500;

		try {
			$result       = $client->getConversionTransactions( $site_id, $filter );
			$transactions = $this->to_array( $result );

			$commission = 0.0;
			foreach ( $transactions as $t ) {
				$t = is_object( $t ) ? $t : (object) $t;
				$commission += (float) ( $t->commission ?? 0 );
			}

			$data = [ 'commission' => $commission, 'count' => count( $transactions ) ];
			$this->cache_set( $cache_key, $data, self::TTL_PENDING );
			return $data;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'soap_error', $e->getMessage() );
		}
	}

	/**
	 * Geeft de meest recente betaling terug.
	 * Retourneert [ 'amount' => float, 'date' => 'Y-m-d' ] of null als geen betaling.
	 */
	public function get_last_payment() {
		$cache_key = 'last_payment';
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$filter               = new \stdClass();
		$filter->billDateFrom = gmdate( 'Y-m-d', strtotime( '-2 years' ) );
		$filter->billDateTo   = gmdate( 'Y-m-d' );

		try {
			$result   = $client->getPayments( $filter );
			$payments = $this->to_array( $result );

			// Zoek de meest recente betaling met een payDate.
			$last = null;
			foreach ( $payments as $p ) {
				$p = is_object( $p ) ? $p : (object) $p;
				if ( empty( $p->payDate ) ) {
					continue;
				}
				$pay_date = substr( (string) $p->payDate, 0, 10 );
				if ( $last === null || $pay_date > $last['date'] ) {
					$last = [
						'amount'   => (float) ( $p->subTotal ?? $p->endTotal ?? 0 ),
						'date'     => $pay_date,
					];
				}
			}

			$result_data = $last ?? [ 'amount' => 0.0, 'date' => '' ];
			$this->cache_set( $cache_key, $result_data, self::TTL_PAYMENTS );
			return $result_data;
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
			// Wrap in array — do NOT cast to (array), that flattens the object's
			// properties instead of keeping the item as a single-element list.
			return [ $value ];
		}
		return [];
	}
}
