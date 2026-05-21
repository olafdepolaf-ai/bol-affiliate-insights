<?php

namespace TuinenBalkon\TBMoneyManager\Google;

/**
 * Thin wrapper around Site Kit's internal PHP API.
 *
 * Site Kit does not publish a stable PHP API, so this class is the single
 * place where we touch Site Kit internals. If Site Kit breaks something, only
 * this file needs to be updated.
 *
 * All public methods return false / WP_Error on failure so callers never need
 * to catch exceptions.
 */
class SiteKitBridge {

	const TRANSIENT_GSC_TOP_PAGES   = 'tbmm_gsc_top_pages';
	const TRANSIENT_ADSENSE_DAILY   = 'tbmm_adsense_daily';
	const CACHE_DURATION            = 6 * HOUR_IN_SECONDS;

	// -------------------------------------------------------------------------
	// Availability checks
	// -------------------------------------------------------------------------

	public function is_available(): bool {
		return class_exists( '\Google\Site_Kit\Plugin' )
			&& null !== \Google\Site_Kit\Plugin::instance();
	}

	public function is_module_connected( string $slug ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}
		try {
			$modules = $this->make_modules();
			return $modules->is_module_active( $slug )
				&& $modules->get_module( $slug )->is_connected();
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function is_search_console_connected(): bool {
		return $this->is_module_connected( 'search-console' );
	}

	public function is_adsense_connected(): bool {
		return $this->is_module_connected( 'adsense' );
	}

	// -------------------------------------------------------------------------
	// Search Console
	// -------------------------------------------------------------------------

	/**
	 * Returns top pages from Search Console (last 30 days), keyed by URL.
	 * Cached for CACHE_DURATION.
	 *
	 * @return array|\WP_Error  Rows (Google_Service_SearchConsole_ApiDataRow[]) or error.
	 */
	public function get_gsc_top_pages( int $limit = 10 ) {
		$cached = get_transient( self::TRANSIENT_GSC_TOP_PAGES );
		if ( false !== $cached ) {
			return $cached;
		}

		try {
			$module = $this->make_modules()->get_module( 'search-console' );
			$result = $module->get_data( 'searchanalytics', array(
				'startDate'  => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'endDate'    => gmdate( 'Y-m-d', strtotime( '-3 days' ) ),
				'dimensions' => array( 'page' ),
				'limit'      => $limit,
			) );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'tbmm_sitekit_gsc', $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$rows = is_array( $result ) ? $result : array();
		set_transient( self::TRANSIENT_GSC_TOP_PAGES, $rows, self::CACHE_DURATION );

		return $rows;
	}

	// -------------------------------------------------------------------------
	// AdSense
	// -------------------------------------------------------------------------

	/**
	 * Returns daily AdSense earnings for the last 35 days as a date => amount map.
	 * Cached for CACHE_DURATION.
	 *
	 * @return array|\WP_Error  [ 'YYYY-MM-DD' => float ] or error.
	 */
	public function get_adsense_daily_earnings() {
		$cached = get_transient( self::TRANSIENT_ADSENSE_DAILY );
		if ( false !== $cached ) {
			return $cached;
		}

		try {
			$module = $this->make_modules()->get_module( 'adsense' );
			$result = $module->get_data( 'report', array(
				'startDate'  => gmdate( 'Y-m-d', strtotime( '-35 days' ) ),
				'endDate'    => gmdate( 'Y-m-d' ),
				'metrics'    => array( 'ESTIMATED_EARNINGS' ),
				'dimensions' => array( 'DATE' ),
			) );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'tbmm_sitekit_adsense', $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$daily = $this->parse_adsense_daily_report( $result );
		set_transient( self::TRANSIENT_ADSENSE_DAILY, $daily, self::CACHE_DURATION );

		return $daily;
	}

	// -------------------------------------------------------------------------
	// Cache management
	// -------------------------------------------------------------------------

	public function clear_gsc_cache(): void {
		delete_transient( self::TRANSIENT_GSC_TOP_PAGES );
	}

	public function clear_adsense_cache(): void {
		delete_transient( self::TRANSIENT_ADSENSE_DAILY );
	}

	public function clear_all_cache(): void {
		$this->clear_gsc_cache();
		$this->clear_adsense_cache();
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private function make_modules(): \Google\Site_Kit\Core\Modules\Modules {
		$context = \Google\Site_Kit\Plugin::instance()->context();
		return new \Google\Site_Kit\Core\Modules\Modules( $context );
	}

	/**
	 * Parses a raw AdSense report response (Google_Service_AdSense_ReportResult
	 * object) into a simple [ 'YYYY-MM-DD' => float ] map.
	 *
	 * Handles both the Google API object format and an array fallback.
	 */
	private function parse_adsense_daily_report( $report ): array {
		$daily = array();

		// Object path (normal Site Kit response).
		if ( is_object( $report ) && method_exists( $report, 'getRows' ) ) {
			foreach ( (array) $report->getRows() as $row ) {
				if ( ! is_object( $row ) || ! method_exists( $row, 'getCells' ) ) {
					continue;
				}
				$cells = (array) $row->getCells();
				if ( count( $cells ) < 2 ) {
					continue;
				}
				$date     = method_exists( $cells[0], 'getValue' ) ? $cells[0]->getValue() : '';
				$earnings = method_exists( $cells[1], 'getValue' ) ? (float) $cells[1]->getValue() : 0.0;
				if ( $date ) {
					$daily[ $date ] = $earnings;
				}
			}
			return $daily;
		}

		// Array fallback (in case Site Kit changes response format).
		if ( is_array( $report ) ) {
			foreach ( $report as $row ) {
				$date     = $row['date'] ?? $row[0] ?? '';
				$earnings = isset( $row['ESTIMATED_EARNINGS'] ) ? (float) $row['ESTIMATED_EARNINGS']
					: ( isset( $row[1] ) ? (float) $row[1] : 0.0 );
				if ( $date ) {
					$daily[ $date ] = $earnings;
				}
			}
		}

		return $daily;
	}
}
