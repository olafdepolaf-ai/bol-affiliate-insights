<?php

namespace TuinenBalkon\AffiliateLinkChecker\Bol\Admin;

use TuinenBalkon\AffiliateLinkChecker\Bol\AffiliateLink\AffiliateLinkAdapterInterface;
use TuinenBalkon\AffiliateLinkChecker\Bol\Service\ApiClient;
use TuinenBalkon\AffiliateLinkChecker\Bol\Service\ReportDataService;
use TuinenBalkon\AffiliateLinkChecker\Bol\Table\CommissionRevenueListTable;
use TuinenBalkon\AffiliateLinkChecker\Bol\Table\OrdersListTable;
use TuinenBalkon\AffiliateLinkChecker\Bol\Table\PromotionMethodsListTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the content of a single Bol.com subtab (no outer wrap or nav).
 * Called by BolTab which owns the subtab navigation.
 */
class SettingsPage {

	private ApiClient                    $api_client;
	private ReportDataService            $report_data_service;
	private AffiliateLinkAdapterInterface $affiliate_adapter;

	public function __construct(
		ApiClient $api_client,
		ReportDataService $report_data_service,
		AffiliateLinkAdapterInterface $affiliate_adapter
	) {
		$this->api_client          = $api_client;
		$this->report_data_service = $report_data_service;
		$this->affiliate_adapter   = $affiliate_adapter;
	}

	/**
	 * Renders the content for the given subtab. No wrapping div or nav.
	 */
	public function render_content( string $active_tab ): void {
		$api_client              = $this->api_client;
		$affiliate_adapter       = $this->affiliate_adapter;
		$credentials             = get_option( 'bol_affiliate_insights_credentials' );
		$global_selected_site_filter = get_option( 'bol_affiliate_insights_selected_website', 'all_sites' );

		$tabs_needing_sites          = array( 'dashboard', 'commission_revenue', 'promotion_methods', 'analysis' );
		$available_sites_for_dropdown = array();
		if ( in_array( $active_tab, $tabs_needing_sites, true ) ) {
			$sites_cache_key = 'bol_available_sites_' . date( 'Y-m-d' );
			$available_sites_for_dropdown = get_transient( $sites_cache_key );
			if ( false === $available_sites_for_dropdown ) {
				$fetched_sites = $api_client->get_available_sites();
				$available_sites_for_dropdown = ( ! empty( $fetched_sites ) && is_array( $fetched_sites ) ) ? $fetched_sites : array();
				set_transient( $sites_cache_key, $available_sites_for_dropdown, DAY_IN_SECONDS );
			}
		}

		// Credentials warning.
		$client_id     = isset( $credentials['client_id'] ) ? trim( $credentials['client_id'] ) : '';
		$client_secret = isset( $credentials['client_secret'] ) ? trim( $credentials['client_secret'] ) : '';
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$settings_url = esc_url( admin_url( 'admin.php?page=affiliate-link-checker&tab=bol&subtab=settings' ) );
			echo '<div class="notice notice-warning"><p><strong>Bol.com:</strong> API credentials niet ingesteld. Ga naar <a href="' . $settings_url . '">Instellingen</a> en voeg je Client ID en Client Secret toe.</p></div>';
		}

		$base_page = 'admin.php?page=affiliate-link-checker&tab=bol';

		if ( $active_tab === 'dashboard' ) {
			$current_period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'last_7_days';
			$today_date_str = current_time( 'Y-m-d' );
			$wp_timezone    = wp_timezone();
			$end_date_obj   = date_create( $today_date_str, $wp_timezone );
			$start_date_obj = date_create( $today_date_str, $wp_timezone );

			if ( $current_period === 'last_7_days' ) {
				date_modify( $end_date_obj, '-1 day' );
				$start_date_obj = clone $end_date_obj;
				date_modify( $start_date_obj, '-6 days' );
			} elseif ( $current_period === 'last_30_days' ) {
				date_modify( $end_date_obj, '-1 day' );
				$start_date_obj = clone $end_date_obj;
				date_modify( $start_date_obj, '-29 days' );
			} elseif ( $current_period === 'this_year' ) {
				$start_date_obj = date_create( date( 'Y-01-01' ), $wp_timezone );
				$end_date_obj   = date_create( date( 'Y-12-31' ), $wp_timezone );
			}

			$start_date = $start_date_obj->format( 'Y-m-d' );
			$end_date   = $end_date_obj->format( 'Y-m-d' );
			?>
			<h3>Dashboard Metrics</h3>
			<div class="dashboard-period-selector">
				Time range:
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=today' ) ); ?>"
				   class="<?php echo $current_period === 'today' ? 'current active' : ''; ?>">Today</a> |
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=last_7_days' ) ); ?>"
				   class="<?php echo $current_period === 'last_7_days' ? 'current active' : ''; ?>">Last 7 Days</a> |
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=last_30_days' ) ); ?>"
				   class="<?php echo $current_period === 'last_30_days' ? 'current active' : ''; ?>">Last 30 Days</a> |
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=this_year' ) ); ?>"
				   class="<?php echo $current_period === 'this_year' ? 'current active' : ''; ?>">This Year</a>
			</div>
			<hr>
			<?php
			$total_orders    = 0;
			$total_clicks    = 0;
			$total_revenue   = 0.0;
			$total_commission = 0.0;
			$conversion_rate = 0.0;
			$error_messages  = array();

			$promo_data_response = $api_client->get_promotion_methods_report( $start_date, $end_date );
			$promo_items         = array();
			if ( is_wp_error( $promo_data_response ) ) {
				$error_messages[] = 'Error fetching promotion data: ' . esc_html( $promo_data_response->get_error_message() );
			} elseif ( ! isset( $promo_data_response['items'] ) ) {
				$error_messages[] = 'Promotion data response is not in the expected format.';
			} else {
				$promo_items = $promo_data_response['items'];
				if ( $global_selected_site_filter !== 'all_sites' && ! empty( $promo_items ) ) {
					$promo_items = array_filter( $promo_items, function ( $item ) use ( $global_selected_site_filter ) {
						return isset( $item['siteCode'] ) && $item['siteCode'] == $global_selected_site_filter;
					} );
				}
				foreach ( $promo_items as $item ) {
					$total_clicks += isset( $item['clicks'] ) ? (int) $item['clicks'] : 0;
					$total_orders += isset( $item['orders'] ) ? (int) $item['orders'] : 0;
					$total_revenue += isset( $item['revenueInclVat'] ) ? (float) $item['revenueInclVat'] : 0.0;
				}
			}

			$orders_report_data = $api_client->get_orders_report( $start_date, $end_date );
			if ( is_wp_error( $orders_report_data ) ) {
				$error_messages[] = 'Error fetching orders report for commission: ' . esc_html( $orders_report_data->get_error_message() );
			} elseif ( ! isset( $orders_report_data['items'] ) ) {
				$error_messages[] = 'Orders report data for commission is not in the expected format.';
			} else {
				foreach ( $orders_report_data['items'] as $order_item ) {
					$total_commission += isset( $order_item['commission'] ) ? (float) $order_item['commission'] : 0.0;
				}
			}

			if ( $total_clicks > 0 ) {
				$conversion_rate = ( $total_orders / $total_clicks ) * 100;
			}

			if ( ! empty( $error_messages ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . implode( '</p><p>', $error_messages ) . '</p></div>';
			}
			?>
			<div class="metrics-container">
				<div class="metric-box"><h4>Orders</h4><p><?php echo number_format_i18n( $total_orders ); ?></p></div>
				<div class="metric-box"><h4>Clicks</h4><p><?php echo number_format_i18n( $total_clicks ); ?></p></div>
				<div class="metric-box"><h4>Revenue</h4><p><?php echo '€' . number_format_i18n( $total_revenue, 2 ); ?></p></div>
				<div class="metric-box"><h4>Commission</h4><p><?php echo '€' . number_format_i18n( $total_commission, 2 ); ?></p></div>
				<div class="metric-box"><h4>Conversion Rate</h4><p><?php echo number_format_i18n( $conversion_rate, 2 ); ?>%</p></div>
			</div>

			<?php
			$saldo_metrics = get_transient( 'bol_saldo_metrics' );
			if ( false === $saldo_metrics ) {
				$saldo_metrics = $this->report_data_service->get_saldo_metrics();
				set_transient( 'bol_saldo_metrics', $saldo_metrics, 3600 );
			}
			?>
			<div class="metrics-container">
				<div class="metric-box"><h4>Goedgekeurd Saldo</h4><p><?php echo '€' . number_format_i18n( $saldo_metrics['approved'], 2 ); ?></p></div>
				<div class="metric-box"><h4>Openstaand Saldo</h4><p><?php echo '€' . number_format_i18n( $saldo_metrics['pending'], 2 ); ?></p></div>
				<div class="metric-box"><h4>Totaal Verwacht Saldo</h4><p><?php echo '€' . number_format_i18n( $saldo_metrics['total'], 2 ); ?></p></div>
			</div>

			<hr>
			<div class="chart-container">
				<h3>Performance Chart</h3>
				<p id="bol-chart-last-updated" style="margin-top:0;"></p>
				<div class="chart-controls">
					<div>
						<label for="chart-metric-selector">Metric:</label>
						<select id="chart-metric-selector">
							<option value="orders" selected>Orders</option>
							<option value="clicks">Clicks</option>
							<option value="revenue">Revenue</option>
							<option value="commission">Commission</option>
							<option value="conversion">Conversion Rate</option>
						</select>
					</div>
					<div>
						<label for="chart-period-selector">Period:</label>
						<select id="chart-period-selector">
							<option value="last_4_weeks" selected>Last 4 Weeks</option>
							<option value="this_month">This Month</option>
							<option value="last_month">Last Month</option>
							<option value="last_30_days">Last 30 Days</option>
							<option value="last_7_days">Last 7 Days</option>
							<option value="this_year">This Year</option>
							<option value="last_year">Last Year</option>
						</select>
					</div>
					<div>
						<label for="chart-granularity-selector">Granularity:</label>
						<select id="chart-granularity-selector">
							<option value="auto" selected>Auto</option>
							<option value="month">Month</option>
							<option value="week">Week</option>
							<option value="day">Day</option>
						</select>
					</div>
					<div>
						<label for="chart-site-selector">Site:</label>
						<select id="chart-site-selector">
							<option value="all_sites" selected>All Sites</option>
							<?php foreach ( $available_sites_for_dropdown as $site_code => $site_name ) : ?>
								<option value="<?php echo esc_attr( $site_code ); ?>">
									<?php echo esc_html( $site_name ); ?> (<?php echo esc_html( $site_code ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<button type="button" id="bol-update-chart-button" class="button button-secondary">Update Chart</button>
					<span id="bol-chart-loading-indicator" class="spinner" style="float:none;display:none;"></span>
				</div>
				<div style="max-width: 800px; margin: auto;">
					<canvas id="bolPerformanceChart"></canvas>
				</div>
				<div id="bol-chart-error-message"></div>
				<div id="bol-chart-data-table-container" style="margin-top: 20px;"></div>
			</div>
			<?php

		} elseif ( $active_tab === 'orders' ) {
			echo '<h3>Orders Report</h3>';
			$current_start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date_create( 'now', wp_timezone() )->modify( '-30 days' )->format( 'Y-m-d' );
			$current_end_date   = isset( $_GET['end_date'] )   ? sanitize_text_field( $_GET['end_date'] )   : current_time( 'Y-m-d' );
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="affiliate-link-checker">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="orders">
				<?php wp_nonce_field( 'bol_orders_date_range', 'bol_orders_nonce' ); ?>
				<label for="orders-start-date">From:</label>
				<input type="text" id="orders-start-date" name="start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
				<label for="orders-end-date">To:</label>
				<input type="text" id="orders-end-date" name="end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
				<input type="submit" value="Fetch Orders" class="button button-secondary">
			</form>
			<hr>
			<?php
			if ( isset( $_GET['start_date'] ) && isset( $_GET['bol_orders_nonce'] ) && wp_verify_nonce( $_GET['bol_orders_nonce'], 'bol_orders_date_range' ) ) {
				echo '<h4>Orders from ' . esc_html( $current_start_date ) . ' to ' . esc_html( $current_end_date ) . '</h4>';
				$orders_data_response = $api_client->get_orders_report( $current_start_date, $current_end_date );
				if ( is_wp_error( $orders_data_response ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html( $orders_data_response->get_error_message() ) . '</p></div>';
				} elseif ( ! isset( $orders_data_response['items'] ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>Orders data response is not in the expected format.</p></div>';
				} else {
					$orders_list_table = new OrdersListTable();
					$orders_list_table->prepare_items( $orders_data_response['items'] );
					echo '<h3>Orders Data</h3>';
					$orders_list_table->display();
				}
			} else {
				echo '<p>Select a date range and click \'Fetch Orders\' to view the report.</p>';
			}

		} elseif ( $active_tab === 'commission_revenue' ) {
			echo '<h3>Commission &amp; Revenue Report</h3>';
			$default_start_date = date_create( current_time( 'Y' ) . '-01-01', wp_timezone() )->format( 'Y-m-d' );
			$default_end_date   = current_time( 'Y-m-d' );
			$current_start_date = isset( $_GET['cr_start_date'] ) ? sanitize_text_field( $_GET['cr_start_date'] ) : $default_start_date;
			$current_end_date   = isset( $_GET['cr_end_date'] )   ? sanitize_text_field( $_GET['cr_end_date'] )   : $default_end_date;
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="affiliate-link-checker">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="commission_revenue">
				<?php wp_nonce_field( 'bol_cr_date_range', 'bol_cr_nonce' ); ?>
				<label for="cr-start-date">From:</label>
				<input type="text" id="cr-start-date" name="cr_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
				<label for="cr-end-date">To:</label>
				<input type="text" id="cr-end-date" name="cr_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
				<input type="submit" value="Fetch Report" class="button button-secondary">
			</form>
			<hr>
			<?php
			if ( isset( $_GET['cr_start_date'] ) && isset( $_GET['bol_cr_nonce'] ) && wp_verify_nonce( $_GET['bol_cr_nonce'], 'bol_cr_date_range' ) ) {
				echo '<h4>Report from ' . esc_html( $current_start_date ) . ' to ' . esc_html( $current_end_date ) . '</h4>';
				$report_data_response = $api_client->get_commission_revenue_report( $current_start_date, $current_end_date );

				if ( is_wp_error( $report_data_response ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html( $report_data_response->get_error_message() ) . '</p></div>';
				} elseif ( ! isset( $report_data_response['items'] ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>Report data response is not in the expected format.</p></div>';
				} else {
					$cr_items = $report_data_response['items'];
					if ( $global_selected_site_filter !== 'all_sites' && ! empty( $cr_items ) ) {
						$cr_items = array_filter( $cr_items, function ( $item ) use ( $global_selected_site_filter ) {
							return isset( $item['siteCode'] ) && $item['siteCode'] == $global_selected_site_filter;
						} );
					}
					$cr_list_table = new CommissionRevenueListTable();
					$cr_list_table->prepare_items( $cr_items );
					echo '<h3>Commission &amp; Revenue Data</h3>';
					$cr_list_table->display();
				}
			} else {
				echo '<p>Select a date range and click \'Fetch Report\' to view the data.</p>';
			}

		} elseif ( $active_tab === 'promotion_methods' ) {
			echo '<h3>Promotion Methods Report</h3>';
			$default_start_date = date_create( current_time( 'Y' ) . '-01-01', wp_timezone() )->format( 'Y-m-d' );
			$default_end_date   = current_time( 'Y-m-d' );
			$current_start_date = isset( $_GET['pm_start_date'] ) ? sanitize_text_field( $_GET['pm_start_date'] ) : $default_start_date;
			$current_end_date   = isset( $_GET['pm_end_date'] )   ? sanitize_text_field( $_GET['pm_end_date'] )   : $default_end_date;
			$only_with_orders   = ! empty( $_GET['pm_only_with_orders'] );
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="affiliate-link-checker">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="promotion_methods">
				<?php wp_nonce_field( 'bol_pm_date_range', 'bol_pm_nonce' ); ?>
				<label for="pm-start-date">From:</label>
				<input type="text" id="pm-start-date" name="pm_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
				<label for="pm-end-date">To:</label>
				<input type="text" id="pm-end-date" name="pm_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
				<label style="margin-left:10px;">
					<input type="checkbox" name="pm_only_with_orders" value="1" <?php checked( $only_with_orders, true ); ?> />
					Only entries with orders
				</label>
				<input type="submit" value="Fetch Report" class="button button-secondary">
			</form>
			<hr>
			<?php
			if ( isset( $_GET['pm_start_date'] ) && isset( $_GET['bol_pm_nonce'] ) && wp_verify_nonce( $_GET['bol_pm_nonce'], 'bol_pm_date_range' ) ) {
				echo '<h4>Report from ' . esc_html( $current_start_date ) . ' to ' . esc_html( $current_end_date ) . '</h4>';
				$report_data_response = $api_client->get_promotion_methods_report( $current_start_date, $current_end_date );

				if ( is_wp_error( $report_data_response ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html( $report_data_response->get_error_message() ) . '</p></div>';
				} elseif ( ! isset( $report_data_response['items'] ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>Report data response is not in the expected format.</p></div>';
				} else {
					$pm_items = $report_data_response['items'];
					if ( $global_selected_site_filter !== 'all_sites' && ! empty( $pm_items ) ) {
						$pm_items = array_filter( $pm_items, function ( $item ) use ( $global_selected_site_filter ) {
							return isset( $item['siteCode'] ) && $item['siteCode'] == $global_selected_site_filter;
						} );
					}
					if ( $only_with_orders && ! empty( $pm_items ) ) {
						$pm_items = array_filter( $pm_items, function ( $item ) {
							return isset( $item['orders'] ) && (int) $item['orders'] > 0;
						} );
					}

					$pm_list_table = new PromotionMethodsListTable();
					$pm_list_table->set_affiliate_link_index( $affiliate_adapter->build_bol_params_index() );
					$pm_list_table->set_hide_site_column( $global_selected_site_filter !== 'all_sites' );
					$pm_list_table->prepare_items( $pm_items );
					echo '<h3>Promotion Methods Data</h3>';
					if ( $only_with_orders ) {
						echo '<p><em>Filter active: only showing rows with at least 1 order.</em></p>';
					}
					$pm_list_table->display();
				}
			} else {
				echo '<p>Select a date range and click \'Fetch Report\' to view the data.</p>';
			}

		} elseif ( $active_tab === 'analysis' ) {
			echo '<h3>Analyse</h3>';

			$default_end_date   = current_time( 'Y-m-d' );
			$default_start_date = date_create( $default_end_date, wp_timezone() )->modify( '-59 days' )->format( 'Y-m-d' );

			$analysis_period = isset( $_GET['an_period'] ) ? sanitize_key( $_GET['an_period'] ) : 'last_60_days';
			if ( ! in_array( $analysis_period, array( 'last_30_days', 'last_60_days', 'last_90_days', 'custom' ), true ) ) {
				$analysis_period = 'last_60_days';
			}

			$analysis_end_date = isset( $_GET['an_end_date'] ) ? sanitize_text_field( $_GET['an_end_date'] ) : $default_end_date;
			if ( empty( $analysis_end_date ) ) {
				$analysis_end_date = $default_end_date;
			}

			if ( 'custom' === $analysis_period ) {
				$analysis_start_date = isset( $_GET['an_start_date'] ) ? sanitize_text_field( $_GET['an_start_date'] ) : $default_start_date;
			} else {
				$end_obj = date_create( $analysis_end_date, wp_timezone() );
				if ( ! $end_obj ) {
					$end_obj           = date_create( $default_end_date, wp_timezone() );
					$analysis_end_date = $default_end_date;
				}
				if ( 'last_30_days' === $analysis_period ) {
					$analysis_start_date = $end_obj->modify( '-29 days' )->format( 'Y-m-d' );
				} elseif ( 'last_90_days' === $analysis_period ) {
					$analysis_start_date = $end_obj->modify( '-89 days' )->format( 'Y-m-d' );
				} else {
					$analysis_start_date = $end_obj->modify( '-59 days' )->format( 'Y-m-d' );
				}
			}

			$min_clicks = isset( $_GET['an_min_clicks'] ) ? (int) $_GET['an_min_clicks'] : 50;
			if ( $min_clicks < 1 ) $min_clicks = 1;
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="affiliate-link-checker">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="analysis">
				<?php wp_nonce_field( 'bol_analysis_filters', 'bol_analysis_nonce' ); ?>
				<label for="an-period" style="margin-right:6px;">Window:</label>
				<select id="an-period" name="an_period">
					<option value="last_30_days" <?php selected( $analysis_period, 'last_30_days' ); ?>>Last 30 days</option>
					<option value="last_60_days" <?php selected( $analysis_period, 'last_60_days' ); ?>>Last 60 days</option>
					<option value="last_90_days" <?php selected( $analysis_period, 'last_90_days' ); ?>>Last 90 days</option>
					<option value="custom"       <?php selected( $analysis_period, 'custom' ); ?>>Custom</option>
				</select>
				<label for="an-start-date">From:</label>
				<input type="text" id="an-start-date" name="an_start_date" class="datepicker" value="<?php echo esc_attr( $analysis_start_date ); ?>">
				<label for="an-end-date">To:</label>
				<input type="text" id="an-end-date" name="an_end_date" class="datepicker" value="<?php echo esc_attr( $analysis_end_date ); ?>">
				<label for="an-min-clicks" style="margin-left:10px;">Min clicks (0 orders):</label>
				<input type="number" id="an-min-clicks" name="an_min_clicks" min="1" step="1" value="<?php echo esc_attr( $min_clicks ); ?>" style="width:90px;">
				<input type="submit" value="Update Analysis" class="button button-secondary">
			</form>
			<hr>
			<?php
			$show_analysis = isset( $_GET['bol_analysis_nonce'] )
				? wp_verify_nonce( $_GET['bol_analysis_nonce'], 'bol_analysis_filters' )
				: true;

			if ( $show_analysis ) {
				$insights = $this->report_data_service->get_analysis_insights( $analysis_start_date, $analysis_end_date, $global_selected_site_filter, $min_clicks );

				if ( isset( $insights['error'] ) && $insights['error'] ) {
					echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html( $insights['error'] ) . '</p></div>';
				}

				echo '<p><em>Based on Promotion Report data. Generated at: ' . esc_html( $insights['generated_at'] ?? '' ) . '</em></p>';

				$bol_params_index = $affiliate_adapter->build_bol_params_index();
				$aff_available    = $affiliate_adapter->is_available();
				$hide_site_col    = ( $global_selected_site_filter !== 'all_sites' );

				$render_table = function ( string $title, array $rows ) use ( $bol_params_index, $aff_available, $hide_site_col, $affiliate_adapter ) {
					echo '<h3>' . esc_html( $title ) . '</h3>';
					if ( empty( $rows ) ) {
						echo '<div class="notice notice-info is-dismissible"><p>No rows found for this selection.</p></div>';
						return;
					}
					echo '<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">';
					echo '<thead><tr>';
					if ( ! $hide_site_col ) echo '<th scope="col" class="manage-column">Site</th>';
					echo '<th scope="col" class="manage-column">Link</th>';
					echo '<th scope="col" class="manage-column">SubID</th>';
					if ( $aff_available ) echo '<th scope="col" class="manage-column">Aff. link</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">Clicks</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">Orders</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">Revenue</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">EPC</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">Conversion</th>';
					echo '</tr></thead><tbody>';

					foreach ( $rows as $row ) {
						$site      = trim( $row['siteName'] ?? '' ) !== ''
							? ( $row['siteName'] . ( $row['siteCode'] ? ' (' . $row['siteCode'] . ')' : '' ) )
							: ( $row['siteCode'] ?? '' );
						$link_name = $row['name'] ?? '';
						$sub_id    = $row['subId'] ?? '';

						echo '<tr>';
						if ( ! $hide_site_col ) echo '<td>' . esc_html( $site ) . '</td>';
						echo '<td>' . esc_html( $link_name ) . '</td>';
						echo '<td>' . esc_html( $sub_id ) . '</td>';

						if ( $aff_available ) {
							$subid_key = strtolower( trim( $sub_id ) );
							$name_key  = strtolower( trim( $link_name ) );
							$matched   = null;
							if ( $subid_key !== '' && isset( $bol_params_index['by_subid'][ $subid_key ] ) ) {
								$matched = $bol_params_index['by_subid'][ $subid_key ];
							} elseif ( $name_key !== '' && isset( $bol_params_index['by_name'][ $name_key ] ) ) {
								$matched = $bol_params_index['by_name'][ $name_key ];
							}

							if ( $matched ) {
								$edit_url   = esc_url( $affiliate_adapter->get_admin_edit_url( (int) $matched['id'] ) );
								$target_url = esc_url( $matched['redirect_url'] ?: $matched['url'] );
								$link_title = esc_attr( $matched['name'] );
								echo '<td>'
									. '<a href="' . $target_url . '" target="_blank" rel="noopener" title="Bekijk: ' . $link_title . '">[&rarr;]</a>'
									. '&nbsp;<a href="' . $edit_url . '" title="Bewerk: ' . $link_title . '">[&#9998;]</a>'
									. '</td>';
							} else {
								echo '<td style="color:#999;">—</td>';
							}
						}

						echo '<td style="text-align:right;">' . number_format_i18n( (int) ( $row['clicks'] ?? 0 ) ) . '</td>';
						echo '<td style="text-align:right;">' . number_format_i18n( (int) ( $row['orders'] ?? 0 ) ) . '</td>';
						echo '<td style="text-align:right;">€' . number_format_i18n( (float) ( $row['revenueInclVat'] ?? 0 ), 2 ) . '</td>';
						echo '<td style="text-align:right;">€' . number_format_i18n( (float) ( $row['epc'] ?? 0 ), 4 ) . '</td>';
						echo '<td style="text-align:right;">' . number_format_i18n( (float) ( $row['conversion'] ?? 0 ), 2 ) . '%</td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
				};

				$render_table( 'Top links op opbrengst (laatste periode)', $insights['top_earning_links'] ?? array() );
				$render_table( 'Veel kliks, 0 orders (mogelijk probleem / optimalisatie-kans)', $insights['high_clicks_no_orders'] ?? array() );
				$render_table( 'Kans om op te schalen: hoge EPC, laag volume (10–150 clicks, >0 orders)', $insights['scale_candidates'] ?? array() );
				$render_table( 'Optimalisatie: veel clicks, lage EPC (≥200 clicks, >0 orders)', $insights['high_volume_low_epc'] ?? array() );
			}

		} elseif ( $active_tab === 'affiliate_links' ) {
			echo '<h3>Bol.com Affiliate Links</h3>';
			$adapter_available = $affiliate_adapter->is_available();
			$all_links         = $adapter_available ? $affiliate_adapter->get_all_links() : array();
			$bol_links         = $adapter_available ? $affiliate_adapter->get_links_by_host( 'bol.com' ) : array();

			echo '<table class="widefat" style="margin-bottom:16px;max-width:600px;"><tbody>';
			echo '<tr><td><strong>Affiliate plugin</strong></td><td>' . esc_html( $affiliate_adapter->get_plugin_name() ) . '</td></tr>';
			echo '<tr><td><strong>Status</strong></td><td>' . ( $adapter_available ? '<span style="color:green;">✔ Actief</span>' : '<span style="color:red;">✘ Niet gevonden</span>' ) . '</td></tr>';
			echo '<tr><td><strong>Totaal links</strong></td><td>' . count( $all_links ) . '</td></tr>';
			echo '<tr><td><strong>Bol.com links</strong></td><td>' . count( $bol_links ) . '</td></tr>';
			echo '</tbody></table>';

			if ( ! $adapter_available ) {
				echo '<div class="notice notice-warning"><p><strong>ThirstyAffiliates niet gevonden.</strong> Zorg dat de plugin actief is.</p></div>';
			} elseif ( empty( $all_links ) ) {
				echo '<div class="notice notice-warning"><p>ThirstyAffiliates is actief maar er zijn nog geen links aangemaakt. <a href="' . esc_url( admin_url( 'post-new.php?post_type=thirstylink' ) ) . '">Voeg een link toe</a>.</p></div>';
			} elseif ( empty( $bol_links ) ) {
				echo '<div class="notice notice-info"><p>Er zijn <strong>' . count( $all_links ) . '</strong> links gevonden, maar geen met een bol.com bestemming.</p></div>';
			} else {
				echo '<p>' . count( $bol_links ) . ' bol.com link(s) gevonden. <a href="' . esc_url( admin_url( 'admin.php?page=thirstyaffiliates' ) ) . '">Beheer alle links in ThirstyAffiliates →</a></p>';
				echo '<table class="wp-list-table widefat striped" style="width:100%;table-layout:auto;">';
				echo '<thead><tr><th>Naam</th><th>Cloaked URL</th><th>Bestemming</th><th>Shortcode</th></tr></thead><tbody>';
				foreach ( $bol_links as $link ) {
					$edit_url  = esc_url( $affiliate_adapter->get_admin_edit_url( $link['id'] ) );
					$redir_url = esc_url( $link['redirect_url'] );
					$dest_url  = esc_url( $link['url'] );
					echo '<tr>';
					echo '<td><strong><a href="' . $edit_url . '">' . esc_html( $link['name'] ) . '</a></strong></td>';
					echo '<td><a href="' . $redir_url . '" target="_blank" rel="noopener">' . esc_html( $link['redirect_url'] ) . '</a></td>';
					echo '<td><a href="' . $dest_url . '" target="_blank" rel="nofollow noopener">[&rarr;]</a></td>';
					echo '<td><code>[thirstylink id="' . (int) $link['id'] . '"]</code></td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}

		} elseif ( $active_tab === 'settings' ) {
			?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'bol_affiliate_insights_options_group' );
				do_settings_sections( 'bol-affiliate-insights-settings' );
				submit_button( 'Save Settings' );
				?>
			</form>
			<hr>
			<h2>Test API Connection</h2>
			<button type="button" id="bol-test-connection-button" class="button">Test Connection</button>
			<div id="bol-test-connection-results"></div>
			<hr>
			<h2>Cache</h2>
			<p>API-data wordt tot 1 uur gecached. Gebruik deze knop om de cache te legen en verse data op te halen bij de volgende paginabezoek.</p>
			<button type="button" id="bol-clear-cache-button" class="button button-secondary">Cache legen</button>
			<span id="bol-clear-cache-result" style="margin-left:10px;"></span>
			<hr>
			<h2>Getting Your API Credentials</h2>
			<p>To obtain your Bol.com Client ID and Client Secret, log in to your Bol.com Partner Program account, go to Account → Open API, and add or view your credentials.</p>
			<?php
		}
	}
}
