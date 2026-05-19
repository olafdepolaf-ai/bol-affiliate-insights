<?php

namespace TuinenBalkon\AffiliateLinkChecker\Bol\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportDataService {

	private ApiClient $api_client;

	public function __construct( ApiClient $api_client ) {
		$this->api_client = $api_client;
	}

	public function get_chart_data( string $metric, string $period, string $granularity, string $site_filter ): array {
		list( $start_date, $end_date ) = $this->calculate_date_range( $period );
		$effective_granularity         = $this->determine_effective_granularity( $granularity, $start_date, $end_date );

		$cache_key = 'bol_chart_' . md5( implode( '|', array( $metric, $period, $effective_granularity, $site_filter ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response_data                          = $this->fetch_and_process_chart_data( $metric, $start_date, $end_date, $effective_granularity, $site_filter );
		$response_data['effective_granularity'] = $effective_granularity;

		set_transient( $cache_key, $response_data, 15 * MINUTE_IN_SECONDS );

		$response_data['generated_at'] = current_time( 'Y-m-d H:i' );

		return $response_data;
	}

	private function calculate_date_range( string $period ): array {
		$end_date = new \DateTimeImmutable( 'now', wp_timezone() );
		switch ( $period ) {
			case 'last_4_weeks':
				return array( $end_date->modify( '-27 days' ), $end_date );
			case 'this_year':
				return array( new \DateTimeImmutable( date( 'Y-01-01' ), wp_timezone() ), new \DateTimeImmutable( 'today', wp_timezone() ) );
			case 'last_year':
				$last_year = (int) date( 'Y' ) - 1;
				return array( new \DateTimeImmutable( $last_year . '-01-01', wp_timezone() ), new \DateTimeImmutable( $last_year . '-12-31', wp_timezone() ) );
			case 'this_month':
				return array( new \DateTimeImmutable( 'first day of this month', wp_timezone() ), new \DateTimeImmutable( 'today', wp_timezone() ) );
			case 'last_month':
				return array( new \DateTimeImmutable( 'first day of last month', wp_timezone() ), new \DateTimeImmutable( 'last day of last month', wp_timezone() ) );
			case 'last_30_days':
				return array( $end_date->modify( '-29 days' ), $end_date );
			case 'last_7_days':
				return array( $end_date->modify( '-6 days' ), $end_date );
			default:
				return array( $end_date->modify( '-27 days' ), $end_date );
		}
	}

	private function determine_effective_granularity( string $granularity, \DateTimeImmutable $start_date, \DateTimeImmutable $end_date ): string {
		if ( $granularity !== 'auto' ) {
			return $granularity;
		}
		$diff_days = $end_date->diff( $start_date )->days;
		if ( $diff_days > 365 ) return 'month';
		if ( $diff_days > 42 )  return 'week';
		return 'day';
	}

	private function fetch_and_process_chart_data( string $metric, \DateTimeImmutable $start_date, \DateTimeImmutable $end_date, string $granularity, string $site_filter ): array {
		$start_date_str = $start_date->format( 'Y-m-d' );
		$end_date_str   = $end_date->format( 'Y-m-d' );

		Logger::debug( 'ReportDataService: fetch_and_process_chart_data', array(
			'start_date'  => $start_date_str,
			'end_date'    => $end_date_str,
			'metric'      => $metric,
			'granularity' => $granularity,
			'site_filter' => $site_filter,
		) );

		if ( $metric === 'commission' ) {
			$response = $this->api_client->get_orders_report( $start_date_str, $end_date_str );
			$date_key = 'orderDateTime';
		} else {
			$response = $this->api_client->get_promotion_methods_report( $start_date_str, $end_date_str );
			$date_key = 'date';
		}

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Error fetching API data: ' . $response->get_error_message() );
		}
		if ( ! isset( $response['items'] ) ) {
			throw new \Exception( 'API response is not in the expected format.' );
		}

		$items = $response['items'];
		if ( $site_filter !== 'all_sites' && $metric !== 'commission' ) {
			$items = array_filter( $items, function ( $item ) use ( $site_filter ) {
				return isset( $item['siteCode'] ) && $item['siteCode'] == $site_filter;
			} );
		}

		return $this->aggregate_chart_data( $items, $metric, $granularity, $start_date, $end_date, $date_key );
	}

	private function initialize_aggregated_data( string $granularity, \DateTimeImmutable $start_date, \DateTimeImmutable $end_date ): array {
		$aggregated_data = array();
		$current_date    = new \DateTimeImmutable( $start_date->format( 'Y-m-d' ), wp_timezone() );
		$end_date_loop   = new \DateTimeImmutable( $end_date->format( 'Y-m-d' ), wp_timezone() );

		while ( $current_date <= $end_date_loop ) {
			$key   = '';
			$label = '';
			switch ( $granularity ) {
				case 'day':
					$key          = $current_date->format( 'Y-m-d' );
					$label        = $current_date->format( 'd M' );
					$current_date = $current_date->add( new \DateInterval( 'P1D' ) );
					break;
				case 'week':
					$key          = $current_date->format( 'Y-W' );
					$label        = 'Week ' . $current_date->format( 'W' );
					$current_date = $current_date->add( new \DateInterval( 'P1W' ) );
					break;
				case 'month':
					$key          = $current_date->format( 'Y-m' );
					$label        = $current_date->format( 'M Y' );
					$current_date = $current_date->add( new \DateInterval( 'P1M' ) );
					break;
			}
			$aggregated_data[ $key ] = array( 'label' => $label, 'value' => 0, 'clicks' => 0, 'orders' => 0 );
		}
		return $aggregated_data;
	}

	private function aggregate_chart_data( array $items, string $metric, string $granularity, \DateTimeImmutable $start_date, \DateTimeImmutable $end_date, string $date_key ): array {
		$aggregated_data = $this->initialize_aggregated_data( $granularity, $start_date, $end_date );

		foreach ( $items as $item ) {
			$item_date = new \DateTimeImmutable( $item[ $date_key ], wp_timezone() );

			if ( $item_date < $start_date || $item_date > $end_date ) {
				continue;
			}

			$key = '';
			switch ( $granularity ) {
				case 'day':
					$key = $item_date->format( 'Y-m-d' );
					break;
				case 'week':
					$key = $item_date->format( 'Y-W' );
					break;
				case 'month':
					$key = $item_date->format( 'Y-m' );
					break;
			}

			if ( ! isset( $aggregated_data[ $key ] ) ) {
				$aggregated_data[ $key ] = array( 'label' => '', 'value' => 0, 'clicks' => 0, 'orders' => 0 );
			}

			switch ( $metric ) {
				case 'commission':
					if ( isset( $item['commission'] ) ) {
						$v = is_string( $item['commission'] ) ? str_replace( ',', '.', $item['commission'] ) : $item['commission'];
						$aggregated_data[ $key ]['value'] += (float) $v;
					}
					break;
				case 'orders':
					$aggregated_data[ $key ]['value'] += 1;
					break;
				case 'clicks':
					if ( isset( $item['clicks'] ) ) {
						$aggregated_data[ $key ]['value'] += (int) $item['clicks'];
					}
					break;
				case 'revenue':
					if ( isset( $item['revenueOriginalInclVat'] ) ) {
						$aggregated_data[ $key ]['value'] += (float) $item['revenueOriginalInclVat'];
					}
					break;
				case 'conversion':
					if ( isset( $item['clicks'] ) ) {
						$aggregated_data[ $key ]['clicks'] += (int) $item['clicks'];
					}
					if ( $date_key === 'orderDateTime' ) {
						$aggregated_data[ $key ]['orders'] += 1;
					}
					break;
			}
		}

		$labels = array();
		$data   = array();
		foreach ( $aggregated_data as $entry ) {
			$labels[] = $entry['label'];
			if ( $metric === 'conversion' ) {
				$conversion_rate = ( $entry['clicks'] > 0 ) ? ( $entry['orders'] / $entry['clicks'] ) * 100 : 0;
				$data[]          = round( $conversion_rate, 2 );
			} else {
				$data[] = $entry['value'];
			}
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => ucfirst( $metric ),
					'data'            => $data,
					'backgroundColor' => 'rgba(0, 115, 170, 0.5)',
					'borderColor'     => 'rgba(0, 115, 170, 1)',
					'borderWidth'     => 1,
				),
			),
		);
	}

	public function get_saldo_metrics(): array {
		$end_date       = new \DateTimeImmutable( 'now', wp_timezone() );
		$start_date     = $end_date->modify( '-89 days' );
		$response       = $this->api_client->get_orders_report( $start_date->format( 'Y-m-d' ), $end_date->format( 'Y-m-d' ) );

		if ( is_wp_error( $response ) || ! isset( $response['items'] ) ) {
			return array( 'approved' => 0, 'pending' => 0, 'total' => 0 );
		}

		$approved_saldo = 0;
		$pending_saldo  = 0;

		foreach ( $response['items'] as $item ) {
			$commission          = isset( $item['commission'] ) ? (float) str_replace( ',', '.', $item['commission'] ) : 0;
			$status              = $item['status'] ?? '';
			$approved_for_payment = $item['approvedForPayment'] ?? false;
			$status_final        = $item['statusFinal'] ?? false;

			if ( $status === 'Geaccepteerd' && ! $approved_for_payment && ! $status_final ) {
				$approved_saldo += $commission;
			}
			if ( $status === 'Open' && ! $approved_for_payment && ! $status_final ) {
				$pending_saldo += $commission;
			}
		}

		return array(
			'approved' => $approved_saldo,
			'pending'  => $pending_saldo,
			'total'    => $approved_saldo + $pending_saldo,
		);
	}

	public function get_analysis_insights( string $start_date_str, string $end_date_str, string $site_filter = 'all_sites', int $min_clicks_for_zero_orders = 50 ): array {
		$cache_key = 'bol_analysis_' . md5( implode( '|', array( $start_date_str, $end_date_str, $site_filter, $min_clicks_for_zero_orders ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->api_client->get_promotion_methods_report( $start_date_str, $end_date_str );
		if ( is_wp_error( $response ) || ! isset( $response['items'] ) || ! is_array( $response['items'] ) ) {
			$result = array(
				'top_earning_links'     => array(),
				'high_clicks_no_orders' => array(),
				'generated_at'          => current_time( 'Y-m-d H:i' ),
				'error'                 => is_wp_error( $response ) ? $response->get_error_message() : 'Unexpected API response format.',
			);
			set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
			return $result;
		}

		$items = $response['items'];
		if ( $site_filter !== 'all_sites' ) {
			$items = array_filter( $items, function ( $item ) use ( $site_filter ) {
				return isset( $item['siteCode'] ) && $item['siteCode'] == $site_filter;
			} );
		}

		$by_link = array();
		foreach ( $items as $item ) {
			$name      = isset( $item['name'] ) ? (string) $item['name'] : '';
			$sub_id    = isset( $item['subId'] ) ? (string) $item['subId'] : '';
			$frame     = isset( $item['frameType'] ) ? (string) $item['frameType'] : '';
			$site_code = isset( $item['siteCode'] ) ? (string) $item['siteCode'] : '';
			$site_name = isset( $item['siteName'] ) ? (string) $item['siteName'] : '';

			if ( $name === '' && $sub_id === '' && $frame === '' ) {
				continue;
			}

			$key = md5( implode( '|', array( $site_code, $name, $sub_id, $frame ) ) );

			if ( ! isset( $by_link[ $key ] ) ) {
				$by_link[ $key ] = array(
					'siteCode'       => $site_code,
					'siteName'       => $site_name,
					'frameType'      => $frame,
					'name'           => $name,
					'subId'          => $sub_id,
					'clicks'         => 0,
					'orders'         => 0,
					'revenueInclVat' => 0.0,
				);
			}

			$by_link[ $key ]['clicks']         += isset( $item['clicks'] ) ? (int) $item['clicks'] : 0;
			$by_link[ $key ]['orders']         += isset( $item['orders'] ) ? (int) $item['orders'] : 0;
			$by_link[ $key ]['revenueInclVat'] += isset( $item['revenueInclVat'] ) ? (float) $item['revenueInclVat'] : 0.0;
		}

		$rows = array_values( $by_link );

		foreach ( $rows as &$row ) {
			$clicks         = max( 0, (int) $row['clicks'] );
			$orders         = max( 0, (int) $row['orders'] );
			$revenue        = (float) $row['revenueInclVat'];
			$row['epc']        = $clicks > 0 ? ( $revenue / $clicks ) : 0.0;
			$row['conversion'] = $clicks > 0 ? ( $orders / $clicks ) * 100 : 0.0;
		}
		unset( $row );

		$top_earning_links = $rows;
		usort( $top_earning_links, function ( $a, $b ) {
			if ( $a['revenueInclVat'] == $b['revenueInclVat'] ) {
				return $b['orders'] <=> $a['orders'];
			}
			return $b['revenueInclVat'] <=> $a['revenueInclVat'];
		} );
		$top_earning_links = array_slice( $top_earning_links, 0, 25 );

		$high_clicks_no_orders = array_values( array_filter( $rows, function ( $row ) use ( $min_clicks_for_zero_orders ) {
			return (int) $row['clicks'] >= $min_clicks_for_zero_orders && (int) $row['orders'] === 0;
		} ) );
		usort( $high_clicks_no_orders, function ( $a, $b ) {
			return $b['clicks'] <=> $a['clicks'];
		} );
		$high_clicks_no_orders = array_slice( $high_clicks_no_orders, 0, 25 );

		$scale_candidates = array_values( array_filter( $rows, function ( $row ) {
			$clicks = (int) ( $row['clicks'] ?? 0 );
			$orders = (int) ( $row['orders'] ?? 0 );
			return $clicks >= 10 && $clicks <= 150 && $orders > 0;
		} ) );
		usort( $scale_candidates, function ( $a, $b ) {
			if ( $a['epc'] == $b['epc'] ) {
				return $b['orders'] <=> $a['orders'];
			}
			return $b['epc'] <=> $a['epc'];
		} );
		$scale_candidates = array_slice( $scale_candidates, 0, 25 );

		$high_volume_low_epc = array_values( array_filter( $rows, function ( $row ) {
			$clicks = (int) ( $row['clicks'] ?? 0 );
			$orders = (int) ( $row['orders'] ?? 0 );
			return $clicks >= 200 && $orders > 0;
		} ) );
		usort( $high_volume_low_epc, function ( $a, $b ) {
			if ( $a['epc'] == $b['epc'] ) {
				return $b['clicks'] <=> $a['clicks'];
			}
			return $a['epc'] <=> $b['epc'];
		} );
		$high_volume_low_epc = array_slice( $high_volume_low_epc, 0, 25 );

		$result = array(
			'top_earning_links'     => $top_earning_links,
			'high_clicks_no_orders' => $high_clicks_no_orders,
			'scale_candidates'      => $scale_candidates,
			'high_volume_low_epc'   => $high_volume_low_epc,
			'generated_at'          => current_time( 'Y-m-d H:i' ),
		);

		set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
		return $result;
	}
}
