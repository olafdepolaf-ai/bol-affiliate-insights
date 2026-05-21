<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Admin;

use TuinenBalkon\TBMoneyManager\Bol\AffiliateLink\AffiliateLinkAdapterInterface;
use TuinenBalkon\TBMoneyManager\Bol\Service\ApiClient;
use TuinenBalkon\TBMoneyManager\Bol\Service\ReportDataService;
use TuinenBalkon\TBMoneyManager\Bol\Table\CommissionRevenueListTable;
use TuinenBalkon\TBMoneyManager\Bol\Table\OrdersListTable;
use TuinenBalkon\TBMoneyManager\Bol\Table\PromotionMethodsListTable;

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

		$tabs_needing_sites          = array( 'dashboard', 'commission_revenue', 'promotion_methods', 'analysis', 'drop_analysis' );
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
			$settings_url = esc_url( admin_url( 'admin.php?page=tb-money-manager&tab=bol&subtab=settings' ) );
			echo '<div class="notice notice-warning"><p>'
				. '<strong>Bol.com:</strong> '
				. sprintf(
					/* translators: %s = link to settings page */
					__( 'API credentials niet ingesteld. Ga naar %s en voeg je Client ID en Client Secret toe.', 'tbmm' ),
					'<a href="' . $settings_url . '">' . esc_html__( 'Instellingen', 'tbmm' ) . '</a>'
				)
				. '</p></div>';
		}

		$base_page = 'admin.php?page=tb-money-manager&tab=bol';

		$csv_export_button = function ( string $label, string $type, string $start, string $end ): void {
			$url = add_query_arg( array(
				'action' => 'tbmm_bol_export_csv',
				'type'   => $type,
				'start'  => $start,
				'end'    => $end,
				'_nonce' => wp_create_nonce( 'tbmm_bol_csv_export' ),
			), admin_url( 'admin-ajax.php' ) );
			echo '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">';
			echo '<h3 style="margin:0">' . esc_html( $label ) . '</h3>';
			/* translators: download button label */
			echo '<a href="' . esc_url( $url ) . '" class="button button-secondary" style="font-size:12px;">&#11015; ' . esc_html__( 'Download CSV', 'tbmm' ) . '</a>';
			echo '</div>';
		};

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
			<h3><?php esc_html_e( 'Dashboard statistieken', 'tbmm' ); ?></h3>
			<div class="dashboard-period-selector">
				<?php esc_html_e( 'Periode:', 'tbmm' ); ?>
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=today' ) ); ?>"
				   class="<?php echo $current_period === 'today' ? 'current active' : ''; ?>"><?php esc_html_e( 'Vandaag', 'tbmm' ); ?></a> |
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=last_7_days' ) ); ?>"
				   class="<?php echo $current_period === 'last_7_days' ? 'current active' : ''; ?>"><?php esc_html_e( 'Laatste 7 dagen', 'tbmm' ); ?></a> |
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=last_30_days' ) ); ?>"
				   class="<?php echo $current_period === 'last_30_days' ? 'current active' : ''; ?>"><?php esc_html_e( 'Laatste 30 dagen', 'tbmm' ); ?></a> |
				<a href="<?php echo esc_url( admin_url( $base_page . '&subtab=dashboard&period=this_year' ) ); ?>"
				   class="<?php echo $current_period === 'this_year' ? 'current active' : ''; ?>"><?php esc_html_e( 'Dit jaar', 'tbmm' ); ?></a>
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
				/* translators: %s = API error message */
				$error_messages[] = sprintf( __( 'Fout bij ophalen promotiedata: %s', 'tbmm' ), esc_html( $promo_data_response->get_error_message() ) );
			} elseif ( ! isset( $promo_data_response['items'] ) ) {
				$error_messages[] = __( 'Onverwacht API-antwoord voor promotiedata.', 'tbmm' );
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
				/* translators: %s = API error message */
				$error_messages[] = sprintf( __( 'Fout bij ophalen orders voor commissie: %s', 'tbmm' ), esc_html( $orders_report_data->get_error_message() ) );
			} elseif ( ! isset( $orders_report_data['items'] ) ) {
				$error_messages[] = __( 'Onverwacht API-antwoord voor orders.', 'tbmm' );
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
				<div class="metric-box"><h4><?php esc_html_e( 'Orders', 'tbmm' ); ?></h4><p><?php echo number_format_i18n( $total_orders ); ?></p></div>
				<div class="metric-box"><h4><?php esc_html_e( 'Kliks', 'tbmm' ); ?></h4><p><?php echo number_format_i18n( $total_clicks ); ?></p></div>
				<div class="metric-box"><h4><?php esc_html_e( 'Omzet', 'tbmm' ); ?></h4><p><?php echo '€' . number_format_i18n( $total_revenue, 2 ); ?></p></div>
				<div class="metric-box"><h4><?php esc_html_e( 'Commissie', 'tbmm' ); ?></h4><p><?php echo '€' . number_format_i18n( $total_commission, 2 ); ?></p></div>
				<div class="metric-box"><h4><?php esc_html_e( 'Conversieratio', 'tbmm' ); ?></h4><p><?php echo number_format_i18n( $conversion_rate, 2 ); ?>%</p></div>
			</div>

			<?php
			$saldo_metrics = get_transient( 'bol_saldo_metrics' );
			if ( false === $saldo_metrics ) {
				$saldo_metrics = $this->report_data_service->get_saldo_metrics();
				set_transient( 'bol_saldo_metrics', $saldo_metrics, 3600 );
			}
			?>
			<div class="metrics-container">
				<div class="metric-box"><h4><?php esc_html_e( 'Goedgekeurd saldo', 'tbmm' ); ?></h4><p><?php echo '€' . number_format_i18n( $saldo_metrics['approved'], 2 ); ?></p></div>
				<div class="metric-box"><h4><?php esc_html_e( 'Openstaand saldo', 'tbmm' ); ?></h4><p><?php echo '€' . number_format_i18n( $saldo_metrics['pending'], 2 ); ?></p></div>
				<div class="metric-box"><h4><?php esc_html_e( 'Totaal verwacht saldo', 'tbmm' ); ?></h4><p><?php echo '€' . number_format_i18n( $saldo_metrics['total'], 2 ); ?></p></div>
			</div>

			<hr>
			<div class="chart-container">
				<h3><?php esc_html_e( 'Prestaties', 'tbmm' ); ?></h3>
				<p id="bol-chart-last-updated" style="margin-top:0;"></p>
				<div class="chart-controls">
					<div>
						<label for="chart-metric-selector"><?php esc_html_e( 'Metriek:', 'tbmm' ); ?></label>
						<select id="chart-metric-selector">
							<option value="orders" selected><?php esc_html_e( 'Orders', 'tbmm' ); ?></option>
							<option value="clicks"><?php esc_html_e( 'Kliks', 'tbmm' ); ?></option>
							<option value="revenue"><?php esc_html_e( 'Omzet', 'tbmm' ); ?></option>
							<option value="commission"><?php esc_html_e( 'Commissie', 'tbmm' ); ?></option>
							<option value="conversion"><?php esc_html_e( 'Conversieratio', 'tbmm' ); ?></option>
						</select>
					</div>
					<div>
						<label for="chart-period-selector"><?php esc_html_e( 'Periode:', 'tbmm' ); ?></label>
						<select id="chart-period-selector">
							<option value="last_4_weeks" selected><?php esc_html_e( 'Laatste 4 weken', 'tbmm' ); ?></option>
							<option value="this_month"><?php esc_html_e( 'Deze maand', 'tbmm' ); ?></option>
							<option value="last_month"><?php esc_html_e( 'Vorige maand', 'tbmm' ); ?></option>
							<option value="last_30_days"><?php esc_html_e( 'Laatste 30 dagen', 'tbmm' ); ?></option>
							<option value="last_7_days"><?php esc_html_e( 'Laatste 7 dagen', 'tbmm' ); ?></option>
							<option value="this_year"><?php esc_html_e( 'Dit jaar', 'tbmm' ); ?></option>
							<option value="last_year"><?php esc_html_e( 'Vorig jaar', 'tbmm' ); ?></option>
						</select>
					</div>
					<div>
						<label for="chart-granularity-selector"><?php esc_html_e( 'Per:', 'tbmm' ); ?></label>
						<select id="chart-granularity-selector">
							<option value="auto"><?php esc_html_e( 'Automatisch', 'tbmm' ); ?></option>
							<option value="month"><?php esc_html_e( 'Maand', 'tbmm' ); ?></option>
							<option value="week" selected><?php esc_html_e( 'Week', 'tbmm' ); ?></option>
							<option value="day"><?php esc_html_e( 'Dag', 'tbmm' ); ?></option>
						</select>
					</div>
					<div>
						<label for="chart-site-selector"><?php esc_html_e( 'Website:', 'tbmm' ); ?></label>
						<select id="chart-site-selector">
							<option value="all_sites"><?php esc_html_e( 'Alle websites', 'tbmm' ); ?></option>
							<?php foreach ( $available_sites_for_dropdown as $site_code => $site_name ) : ?>
								<option value="<?php echo esc_attr( $site_code ); ?>">
									<?php echo esc_html( $site_name ); ?> (<?php echo esc_html( $site_code ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<button type="button" id="bol-update-chart-button" class="button button-secondary"><?php esc_html_e( 'Grafiek bijwerken', 'tbmm' ); ?></button>
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
			echo '<h3>' . esc_html__( 'Orders rapport', 'tbmm' ) . '</h3>';
			$current_start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date_create( 'now', wp_timezone() )->modify( '-30 days' )->format( 'Y-m-d' );
			$current_end_date   = isset( $_GET['end_date'] )   ? sanitize_text_field( $_GET['end_date'] )   : current_time( 'Y-m-d' );
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="tb-money-manager">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="orders">
				<?php wp_nonce_field( 'bol_orders_date_range', 'bol_orders_nonce' ); ?>
				<label for="orders-start-date"><?php esc_html_e( 'Van:', 'tbmm' ); ?></label>
				<input type="text" id="orders-start-date" name="start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
				<label for="orders-end-date"><?php esc_html_e( 't/m:', 'tbmm' ); ?></label>
				<input type="text" id="orders-end-date" name="end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
				<input type="submit" value="<?php esc_attr_e( 'Ophalen', 'tbmm' ); ?>" class="button button-secondary">
			</form>
			<hr>
			<?php
			if ( isset( $_GET['start_date'] ) && isset( $_GET['bol_orders_nonce'] ) && wp_verify_nonce( $_GET['bol_orders_nonce'], 'bol_orders_date_range' ) ) {
				/* translators: 1: start date, 2: end date */
				echo '<h4>' . sprintf( esc_html__( 'Orders van %1$s t/m %2$s', 'tbmm' ), esc_html( $current_start_date ), esc_html( $current_end_date ) ) . '</h4>';
				$orders_data_response = $api_client->get_orders_report( $current_start_date, $current_end_date );
				if ( is_wp_error( $orders_data_response ) ) {
					/* translators: %s = error message */
					echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( esc_html__( 'Fout: %s', 'tbmm' ), esc_html( $orders_data_response->get_error_message() ) ) . '</p></div>';
				} elseif ( ! isset( $orders_data_response['items'] ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'API-respons heeft een onverwacht formaat.', 'tbmm' ) . '</p></div>';
				} else {
					$orders_list_table = new OrdersListTable();
					$orders_list_table->prepare_items( $orders_data_response['items'] );
					$csv_export_button( __( 'Orders', 'tbmm' ), 'orders', $current_start_date, $current_end_date );
					$orders_list_table->display();
				}
			} else {
				echo '<p>' . esc_html__( 'Kies een datumbereik en klik op Ophalen.', 'tbmm' ) . '</p>';
			}

		} elseif ( $active_tab === 'commission_revenue' ) {
			echo '<h3>' . esc_html__( 'Commissie & Omzet rapport', 'tbmm' ) . '</h3>';
			$default_start_date = date_create( current_time( 'Y' ) . '-01-01', wp_timezone() )->format( 'Y-m-d' );
			$default_end_date   = current_time( 'Y-m-d' );
			$current_start_date = isset( $_GET['cr_start_date'] ) ? sanitize_text_field( $_GET['cr_start_date'] ) : $default_start_date;
			$current_end_date   = isset( $_GET['cr_end_date'] )   ? sanitize_text_field( $_GET['cr_end_date'] )   : $default_end_date;
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="tb-money-manager">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="commission_revenue">
				<?php wp_nonce_field( 'bol_cr_date_range', 'bol_cr_nonce' ); ?>
				<label for="cr-start-date"><?php esc_html_e( 'Van:', 'tbmm' ); ?></label>
				<input type="text" id="cr-start-date" name="cr_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
				<label for="cr-end-date"><?php esc_html_e( 't/m:', 'tbmm' ); ?></label>
				<input type="text" id="cr-end-date" name="cr_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
				<input type="submit" value="<?php esc_attr_e( 'Ophalen', 'tbmm' ); ?>" class="button button-secondary">
			</form>
			<hr>
			<?php
			if ( isset( $_GET['cr_start_date'] ) && isset( $_GET['bol_cr_nonce'] ) && wp_verify_nonce( $_GET['bol_cr_nonce'], 'bol_cr_date_range' ) ) {
				/* translators: 1: start date, 2: end date */
				echo '<h4>' . sprintf( esc_html__( 'Rapport van %1$s t/m %2$s', 'tbmm' ), esc_html( $current_start_date ), esc_html( $current_end_date ) ) . '</h4>';
				$report_data_response = $api_client->get_commission_revenue_report( $current_start_date, $current_end_date );

				if ( is_wp_error( $report_data_response ) ) {
					/* translators: %s = error message */
					echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( esc_html__( 'Fout: %s', 'tbmm' ), esc_html( $report_data_response->get_error_message() ) ) . '</p></div>';
				} elseif ( ! isset( $report_data_response['items'] ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'API-respons heeft een onverwacht formaat.', 'tbmm' ) . '</p></div>';
				} else {
					$cr_items = $report_data_response['items'];
					if ( $global_selected_site_filter !== 'all_sites' && ! empty( $cr_items ) ) {
						$cr_items = array_filter( $cr_items, function ( $item ) use ( $global_selected_site_filter ) {
							return isset( $item['siteCode'] ) && $item['siteCode'] == $global_selected_site_filter;
						} );
					}
					$cr_list_table = new CommissionRevenueListTable();
					$cr_list_table->prepare_items( $cr_items );
					$csv_export_button( __( 'Commissie & Omzet', 'tbmm' ), 'commission-revenue', $current_start_date, $current_end_date );
					$cr_list_table->display();
				}
			} else {
				echo '<p>' . esc_html__( 'Kies een datumbereik en klik op Ophalen.', 'tbmm' ) . '</p>';
			}

		} elseif ( $active_tab === 'promotion_methods' ) {
			echo '<h3>' . esc_html__( 'Promotiemethoden rapport', 'tbmm' ) . '</h3>';
			$default_start_date = date_create( current_time( 'Y' ) . '-01-01', wp_timezone() )->format( 'Y-m-d' );
			$default_end_date   = current_time( 'Y-m-d' );
			$current_start_date = isset( $_GET['pm_start_date'] ) ? sanitize_text_field( $_GET['pm_start_date'] ) : $default_start_date;
			$current_end_date   = isset( $_GET['pm_end_date'] )   ? sanitize_text_field( $_GET['pm_end_date'] )   : $default_end_date;
			$only_with_orders   = ! empty( $_GET['pm_only_with_orders'] );
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="tb-money-manager">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="promotion_methods">
				<?php wp_nonce_field( 'bol_pm_date_range', 'bol_pm_nonce' ); ?>
				<label for="pm-start-date"><?php esc_html_e( 'Van:', 'tbmm' ); ?></label>
				<input type="text" id="pm-start-date" name="pm_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
				<label for="pm-end-date"><?php esc_html_e( 't/m:', 'tbmm' ); ?></label>
				<input type="text" id="pm-end-date" name="pm_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
				<label style="margin-left:10px;">
					<input type="checkbox" name="pm_only_with_orders" value="1" <?php checked( $only_with_orders, true ); ?> />
					<?php esc_html_e( 'Alleen rijen met orders', 'tbmm' ); ?>
				</label>
				<input type="submit" value="<?php esc_attr_e( 'Ophalen', 'tbmm' ); ?>" class="button button-secondary">
			</form>
			<hr>
			<?php
			if ( isset( $_GET['pm_start_date'] ) && isset( $_GET['bol_pm_nonce'] ) && wp_verify_nonce( $_GET['bol_pm_nonce'], 'bol_pm_date_range' ) ) {
				/* translators: 1: start date, 2: end date */
				echo '<h4>' . sprintf( esc_html__( 'Rapport van %1$s t/m %2$s', 'tbmm' ), esc_html( $current_start_date ), esc_html( $current_end_date ) ) . '</h4>';
				$report_data_response = $api_client->get_promotion_methods_report( $current_start_date, $current_end_date );

				if ( is_wp_error( $report_data_response ) ) {
					/* translators: %s = error message */
					echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( esc_html__( 'Fout: %s', 'tbmm' ), esc_html( $report_data_response->get_error_message() ) ) . '</p></div>';
				} elseif ( ! isset( $report_data_response['items'] ) ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'API-respons heeft een onverwacht formaat.', 'tbmm' ) . '</p></div>';
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
					$csv_export_button( __( 'Promotiemethoden', 'tbmm' ), 'promotion-methods', $current_start_date, $current_end_date );
					if ( $only_with_orders ) {
						echo '<p><em>' . esc_html__( 'Filter actief: alleen rijen met minstens 1 order.', 'tbmm' ) . '</em></p>';
					}
					$pm_list_table->display();
				}
			} else {
				echo '<p>' . esc_html__( 'Kies een datumbereik en klik op Ophalen.', 'tbmm' ) . '</p>';
			}

		} elseif ( $active_tab === 'analysis' ) {
			echo '<h3>' . esc_html__( 'Analyse', 'tbmm' ) . '</h3>';

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
				<input type="hidden" name="page" value="tb-money-manager">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="analysis">
				<?php wp_nonce_field( 'bol_analysis_filters', 'bol_analysis_nonce' ); ?>
				<label for="an-period" style="margin-right:6px;"><?php esc_html_e( 'Periode:', 'tbmm' ); ?></label>
				<select id="an-period" name="an_period">
					<option value="last_30_days" <?php selected( $analysis_period, 'last_30_days' ); ?>><?php esc_html_e( 'Laatste 30 dagen', 'tbmm' ); ?></option>
					<option value="last_60_days" <?php selected( $analysis_period, 'last_60_days' ); ?>><?php esc_html_e( 'Laatste 60 dagen', 'tbmm' ); ?></option>
					<option value="last_90_days" <?php selected( $analysis_period, 'last_90_days' ); ?>><?php esc_html_e( 'Laatste 90 dagen', 'tbmm' ); ?></option>
					<option value="custom"       <?php selected( $analysis_period, 'custom' ); ?>><?php esc_html_e( 'Aangepast', 'tbmm' ); ?></option>
				</select>
				<label for="an-start-date"><?php esc_html_e( 'Van:', 'tbmm' ); ?></label>
				<input type="text" id="an-start-date" name="an_start_date" class="datepicker" value="<?php echo esc_attr( $analysis_start_date ); ?>">
				<label for="an-end-date"><?php esc_html_e( 't/m:', 'tbmm' ); ?></label>
				<input type="text" id="an-end-date" name="an_end_date" class="datepicker" value="<?php echo esc_attr( $analysis_end_date ); ?>">
				<label for="an-min-clicks" style="margin-left:10px;"><?php esc_html_e( 'Min. kliks (0 orders):', 'tbmm' ); ?></label>
				<input type="number" id="an-min-clicks" name="an_min_clicks" min="1" step="1" value="<?php echo esc_attr( $min_clicks ); ?>" style="width:90px;">
				<input type="submit" value="<?php esc_attr_e( 'Analyseer', 'tbmm' ); ?>" class="button button-secondary">
			</form>
			<hr>
			<?php
			$show_analysis = isset( $_GET['bol_analysis_nonce'] )
				? wp_verify_nonce( $_GET['bol_analysis_nonce'], 'bol_analysis_filters' )
				: true;

			if ( $show_analysis ) {
				$insights = $this->report_data_service->get_analysis_insights( $analysis_start_date, $analysis_end_date, $global_selected_site_filter, $min_clicks );

				if ( isset( $insights['error'] ) && $insights['error'] ) {
					/* translators: %s = error message */
					echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( esc_html__( 'Fout: %s', 'tbmm' ), esc_html( $insights['error'] ) ) . '</p></div>';
				}

				/* translators: %s = datetime string */
				echo '<p><em>' . sprintf( esc_html__( 'Op basis van promotie-rapport. Gegenereerd om: %s', 'tbmm' ), esc_html( $insights['generated_at'] ?? '' ) ) . '</em></p>';

				$bol_params_index = $affiliate_adapter->build_bol_params_index();
				$aff_available    = $affiliate_adapter->is_available();
				$hide_site_col    = ( $global_selected_site_filter !== 'all_sites' );

				$render_table = function ( string $title, array $rows ) use ( $bol_params_index, $aff_available, $hide_site_col, $affiliate_adapter ) {
					echo '<h3>' . esc_html( $title ) . '</h3>';
					if ( empty( $rows ) ) {
						echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Geen rijen gevonden voor deze selectie.', 'tbmm' ) . '</p></div>';
						return;
					}
					echo '<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">';
					echo '<thead><tr>';
					if ( ! $hide_site_col ) echo '<th scope="col" class="manage-column">' . esc_html__( 'Site', 'tbmm' ) . '</th>';
					echo '<th scope="col" class="manage-column">' . esc_html__( 'Link', 'tbmm' ) . '</th>';
					echo '<th scope="col" class="manage-column">' . esc_html__( 'SubID', 'tbmm' ) . '</th>';
					if ( $aff_available ) echo '<th scope="col" class="manage-column">' . esc_html__( 'Aff. link', 'tbmm' ) . '</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">' . esc_html__( 'Kliks', 'tbmm' ) . '</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">' . esc_html__( 'Orders', 'tbmm' ) . '</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">' . esc_html__( 'Omzet', 'tbmm' ) . '</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">' . esc_html__( 'EPC', 'tbmm' ) . '</th>';
					echo '<th scope="col" class="manage-column" style="text-align:right;">' . esc_html__( 'Conversie', 'tbmm' ) . '</th>';
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
									/* translators: link title attribute */
									. '<a href="' . $target_url . '" target="_blank" rel="noopener" title="' . esc_attr( sprintf( __( 'Bekijk: %s', 'tbmm' ), $matched['name'] ) ) . '">[&rarr;]</a>'
									. '&nbsp;<a href="' . $edit_url . '" title="' . esc_attr( sprintf( __( 'Bewerk: %s', 'tbmm' ), $matched['name'] ) ) . '">[&#9998;]</a>'
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

				$render_table( __( 'Top links op opbrengst (laatste periode)', 'tbmm' ), $insights['top_earning_links'] ?? array() );
				$render_table( __( 'Veel kliks, 0 orders (mogelijk probleem / optimalisatie-kans)', 'tbmm' ), $insights['high_clicks_no_orders'] ?? array() );
				$render_table( __( 'Kans om op te schalen: hoge EPC, laag volume (10–150 kliks, >0 orders)', 'tbmm' ), $insights['scale_candidates'] ?? array() );
				$render_table( __( 'Optimalisatie: veel kliks, lage EPC (≥200 kliks, >0 orders)', 'tbmm' ), $insights['high_volume_low_epc'] ?? array() );
			}

		} elseif ( $active_tab === 'drop_analysis' ) {
			echo '<h3>' . esc_html__( 'Klik-drop analyse', 'tbmm' ) . '</h3>';
			echo '<p>' . wp_kses( __( 'Vergelijkt kliks <strong>vóór</strong> vs <strong>ná</strong> een opgegeven breekdatum, per promotionmethod. Kliks/dag maakt de periodes vergelijkbaar ongeacht lengte.', 'tbmm' ), array( 'strong' => array() ) ) . '</p>';

			$default_break  = '2026-04-12';
			$default_before = '2026-01-01';
			$default_after  = current_time( 'Y-m-d' );

			$da_before_start = isset( $_GET['da_before_start'] ) ? sanitize_text_field( $_GET['da_before_start'] ) : $default_before;
			$da_before_end   = isset( $_GET['da_before_end']   ) ? sanitize_text_field( $_GET['da_before_end']   ) : date( 'Y-m-d', strtotime( $default_break . ' -1 day' ) );
			$da_after_start  = isset( $_GET['da_after_start']  ) ? sanitize_text_field( $_GET['da_after_start']  ) : $default_break;
			$da_after_end    = isset( $_GET['da_after_end']    ) ? sanitize_text_field( $_GET['da_after_end']    ) : $default_after;
			?>
			<form method="GET" action="">
				<input type="hidden" name="page" value="tb-money-manager">
				<input type="hidden" name="tab" value="bol">
				<input type="hidden" name="subtab" value="drop_analysis">
				<?php wp_nonce_field( 'bol_da_filters', 'bol_da_nonce' ); ?>
				<table style="border-collapse:collapse;margin-bottom:12px;">
					<tr>
						<td style="padding:4px 12px 4px 0;font-weight:600;"><?php esc_html_e( 'Periode vóór:', 'tbmm' ); ?></td>
						<td style="padding:4px 8px 4px 0;"><label><?php esc_html_e( 'Van', 'tbmm' ); ?> <input type="text" name="da_before_start" class="datepicker" value="<?php echo esc_attr( $da_before_start ); ?>" style="width:120px;"></label></td>
						<td style="padding:4px 8px 4px 0;"><label><?php esc_html_e( 't/m', 'tbmm' ); ?> <input type="text" name="da_before_end" class="datepicker" value="<?php echo esc_attr( $da_before_end ); ?>" style="width:120px;"></label></td>
					</tr>
					<tr>
						<td style="padding:4px 12px 4px 0;font-weight:600;"><?php esc_html_e( 'Periode ná:', 'tbmm' ); ?></td>
						<td style="padding:4px 8px 4px 0;"><label><?php esc_html_e( 'Van', 'tbmm' ); ?> <input type="text" name="da_after_start" class="datepicker" value="<?php echo esc_attr( $da_after_start ); ?>" style="width:120px;"></label></td>
						<td style="padding:4px 8px 4px 0;"><label><?php esc_html_e( 't/m', 'tbmm' ); ?> <input type="text" name="da_after_end" class="datepicker" value="<?php echo esc_attr( $da_after_end ); ?>" style="width:120px;"></label></td>
					</tr>
				</table>
				<input type="submit" value="<?php esc_attr_e( 'Analyseer', 'tbmm' ); ?>" class="button button-primary">
			</form>
			<hr>
			<?php
			$run_da = isset( $_GET['bol_da_nonce'] )
				? wp_verify_nonce( $_GET['bol_da_nonce'], 'bol_da_filters' )
				: true;

			if ( $run_da ) {
				$days_before = max( 1, (int) ( ( strtotime( $da_before_end ) - strtotime( $da_before_start ) ) / DAY_IN_SECONDS ) + 1 );
				$days_after  = max( 1, (int) ( ( strtotime( $da_after_end )  - strtotime( $da_after_start )  ) / DAY_IN_SECONDS ) + 1 );

				$raw_before = $api_client->get_promotion_methods_report( $da_before_start, $da_before_end );
				$raw_after  = $api_client->get_promotion_methods_report( $da_after_start,  $da_after_end  );

				$da_error = null;
				/* translators: %s = error message */
				if ( is_wp_error( $raw_before ) ) $da_error = sprintf( __( 'Periode vóór: %s', 'tbmm' ), $raw_before->get_error_message() );
				if ( is_wp_error( $raw_after  ) ) $da_error = sprintf( __( 'Periode ná: %s', 'tbmm' ),   $raw_after->get_error_message() );

				if ( $da_error ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $da_error ) . '</p></div>';
				} else {
					$aggregate = function ( array $items ) use ( $global_selected_site_filter ): array {
						$out = array();
						foreach ( $items as $item ) {
							if ( $global_selected_site_filter !== 'all_sites' && ( $item['siteCode'] ?? '' ) != $global_selected_site_filter ) continue;
							$name  = (string) ( $item['name']  ?? '' );
							$subid = (string) ( $item['subId'] ?? '' );
							$key   = $name . '||' . $subid;
							if ( ! isset( $out[ $key ] ) ) {
								$out[ $key ] = array( 'name' => $name, 'subId' => $subid, 'clicks' => 0 );
							}
							$out[ $key ]['clicks'] += (int) ( $item['clicks'] ?? 0 );
						}
						return $out;
					};

					$agg_before = $aggregate( $raw_before['items'] ?? array() );
					$agg_after  = $aggregate( $raw_after['items']  ?? array() );
					$all_keys   = array_unique( array_merge( array_keys( $agg_before ), array_keys( $agg_after ) ) );

					$rows = array();
					foreach ( $all_keys as $key ) {
						$c_before = $agg_before[ $key ]['clicks'] ?? 0;
						$c_after  = $agg_after[ $key ]['clicks']  ?? 0;
						if ( $c_before === 0 && $c_after === 0 ) continue;

						$cpd_before  = round( $c_before / $days_before, 2 );
						$cpd_after   = round( $c_after  / $days_after,  2 );
						$delta       = round( $cpd_after - $cpd_before, 2 );
						$pct         = $cpd_before > 0 ? (int) round( ( $delta / $cpd_before ) * 100 ) : ( $cpd_after > 0 ? PHP_INT_MAX : 0 );
						$name        = $agg_before[ $key ]['name']  ?? $agg_after[ $key ]['name']  ?? '';
						$subid       = $agg_before[ $key ]['subId'] ?? $agg_after[ $key ]['subId'] ?? '';

						$rows[] = array( 'name' => $name, 'subId' => $subid, 'c_before' => $c_before, 'c_after' => $c_after, 'cpd_before' => $cpd_before, 'cpd_after' => $cpd_after, 'delta' => $delta, 'pct' => $pct );
					}

					usort( $rows, fn( $a, $b ) => $a['delta'] <=> $b['delta'] );

					$total_before = array_sum( array_column( $rows, 'c_before' ) );
					$total_after  = array_sum( array_column( $rows, 'c_after' ) );

					echo '<table class="widefat" style="max-width:560px;margin-bottom:20px;"><tbody>';
					/* translators: 1: start date, 2: end date, 3: days, 4: clicks, 5: clicks/day */
					printf( '<tr><th>' . esc_html__( 'Periode vóór', 'tbmm' ) . '</th><td>%s – %s (%d ' . esc_html__( 'dagen', 'tbmm' ) . ', %d ' . esc_html__( 'kliks totaal', 'tbmm' ) . ', %.1f/dag)</td></tr>', esc_html( $da_before_start ), esc_html( $da_before_end ), $days_before, $total_before, $total_before / $days_before );
					printf( '<tr><th>' . esc_html__( 'Periode ná', 'tbmm' ) . '</th><td>%s – %s (%d ' . esc_html__( 'dagen', 'tbmm' ) . ', %d ' . esc_html__( 'kliks totaal', 'tbmm' ) . ', %.1f/dag)</td></tr>', esc_html( $da_after_start  ), esc_html( $da_after_end   ), $days_after,  $total_after,  $total_after  / $days_after  );
					printf( '<tr><th>' . esc_html__( 'Totale daling', 'tbmm' ) . '</th><td><strong>%.1f ' . esc_html__( 'kliks/dag', 'tbmm' ) . '</strong> (%.0f%%)</td></tr>', ( $total_after / $days_after ) - ( $total_before / $days_before ), $total_before > 0 ? ( ( ( $total_after / $days_after ) - ( $total_before / $days_before ) ) / ( $total_before / $days_before ) ) * 100 : 0 );
					echo '</tbody></table>';

					echo '<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">';
					echo '<thead><tr>';
					echo '<th>#</th>';
					echo '<th>' . esc_html__( 'Link (name)', 'tbmm' ) . '</th>';
					echo '<th>' . esc_html__( 'SubID', 'tbmm' ) . '</th>';
					echo '<th style="text-align:right;">' . esc_html__( 'Kliks vóór', 'tbmm' ) . '</th>';
					echo '<th style="text-align:right;">' . esc_html__( 'Kliks ná', 'tbmm' ) . '</th>';
					echo '<th style="text-align:right;">' . esc_html__( 'Kliks/dag vóór', 'tbmm' ) . '</th>';
					echo '<th style="text-align:right;">' . esc_html__( 'Kliks/dag ná', 'tbmm' ) . '</th>';
					echo '<th style="text-align:right;">Δ/dag</th>';
					echo '<th style="text-align:right;">%</th>';
					echo '</tr></thead><tbody>';

					$i = 1;
					foreach ( $rows as $r ) {
						$is_drop = $r['delta'] < -0.1;
						$is_rise = $r['delta'] > 0.1;
						$color   = $is_drop ? '#b32d2e' : ( $is_rise ? '#00a32a' : '#646970' );
						$bg      = ( $is_drop && $r['cpd_before'] >= 0.5 ) ? ' style="background:#fff5f5"' : '';
						/* translators: label for a new link (no historical data) */
						$pct_str = $r['pct'] === PHP_INT_MAX ? esc_html__( 'nieuw', 'tbmm' ) : $r['pct'] . '%';
						$delta_s = ( $r['delta'] >= 0 ? '+' : '' ) . $r['delta'];

						echo '<tr' . $bg . '>';
						echo '<td style="color:#aaa">' . $i . '</td>';
						echo '<td><strong>' . esc_html( $r['name']  ) . '</strong></td>';
						echo '<td><code style="font-size:11px;background:#f0f0f0;padding:1px 4px;border-radius:3px;">' . esc_html( $r['subId'] ) . '</code></td>';
						echo '<td style="text-align:right;">' . number_format_i18n( $r['c_before']   ) . '</td>';
						echo '<td style="text-align:right;">' . number_format_i18n( $r['c_after']    ) . '</td>';
						echo '<td style="text-align:right;">' . number_format_i18n( $r['cpd_before'], 2 ) . '</td>';
						echo '<td style="text-align:right;">' . number_format_i18n( $r['cpd_after'],  2 ) . '</td>';
						echo '<td style="text-align:right;font-weight:600;color:' . $color . '">' . esc_html( $delta_s ) . '</td>';
						echo '<td style="text-align:right;color:' . $color . '">' . esc_html( $pct_str ) . '</td>';
						echo '</tr>';
						$i++;
					}

					echo '</tbody></table>';
					echo '<p style="margin-top:8px;color:#646970;font-size:12px;">' . esc_html__( 'Gesorteerd op grootste klik-daling bovenaan. Rood gemarkeerd = significante dalers (≥0,5 kliks/dag vóór).', 'tbmm' ) . '</p>';
				}
			}

		} elseif ( $active_tab === 'affiliate_links' ) {
			echo '<h3>' . esc_html__( 'Bol.com Affiliate Links', 'tbmm' ) . '</h3>';
			$adapter_available = $affiliate_adapter->is_available();
			$all_links         = $adapter_available ? $affiliate_adapter->get_all_links() : array();
			$bol_links         = $adapter_available ? $affiliate_adapter->get_links_by_host( 'bol.com' ) : array();

			echo '<table class="widefat" style="margin-bottom:16px;max-width:600px;"><tbody>';
			echo '<tr><td><strong>' . esc_html__( 'Affiliate plugin', 'tbmm' ) . '</strong></td><td>' . esc_html( $affiliate_adapter->get_plugin_name() ) . '</td></tr>';
			echo '<tr><td><strong>' . esc_html__( 'Status', 'tbmm' ) . '</strong></td><td>' . ( $adapter_available ? '<span style="color:green;">✔ ' . esc_html__( 'Actief', 'tbmm' ) . '</span>' : '<span style="color:red;">✘ ' . esc_html__( 'Niet gevonden', 'tbmm' ) . '</span>' ) . '</td></tr>';
			echo '<tr><td><strong>' . esc_html__( 'Totaal links', 'tbmm' ) . '</strong></td><td>' . count( $all_links ) . '</td></tr>';
			echo '<tr><td><strong>' . esc_html__( 'Bol.com links', 'tbmm' ) . '</strong></td><td>' . count( $bol_links ) . '</td></tr>';
			echo '</tbody></table>';

			if ( ! $adapter_available ) {
				echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'ThirstyAffiliates niet gevonden.', 'tbmm' ) . '</strong> ' . esc_html__( 'Zorg dat de plugin actief is.', 'tbmm' ) . '</p></div>';
			} elseif ( empty( $all_links ) ) {
				/* translators: %s = link to add new affiliate link */
				echo '<div class="notice notice-warning"><p>' . sprintf( __( 'ThirstyAffiliates is actief maar er zijn nog geen links aangemaakt. %s.', 'tbmm' ), '<a href="' . esc_url( admin_url( 'post-new.php?post_type=thirstylink' ) ) . '">' . esc_html__( 'Voeg een link toe', 'tbmm' ) . '</a>' ) . '</p></div>';
			} elseif ( empty( $bol_links ) ) {
				/* translators: %d = number of links found */
				echo '<div class="notice notice-info"><p>' . sprintf( __( 'Er zijn <strong>%d</strong> links gevonden, maar geen met een bol.com bestemming.', 'tbmm' ), count( $all_links ) ) . '</p></div>';
			} else {
				/* translators: 1: count of bol.com links, 2: link to ThirstyAffiliates admin */
				echo '<p>' . sprintf( __( '%1$d bol.com link(s) gevonden. %2$s', 'tbmm' ), count( $bol_links ), '<a href="' . esc_url( admin_url( 'admin.php?page=thirstyaffiliates' ) ) . '">' . esc_html__( 'Beheer alle links in ThirstyAffiliates →', 'tbmm' ) . '</a>' ) . '</p>';
				echo '<table class="wp-list-table widefat striped" style="width:100%;table-layout:auto;">';
				echo '<thead><tr>'
					. '<th>' . esc_html__( 'Naam', 'tbmm' ) . '</th>'
					. '<th>' . esc_html__( 'Cloaked URL', 'tbmm' ) . '</th>'
					. '<th>' . esc_html__( 'Bestemming', 'tbmm' ) . '</th>'
					. '<th>' . esc_html__( 'Shortcode', 'tbmm' ) . '</th>'
					. '</tr></thead><tbody>';
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

		} elseif ( $active_tab === 'link_generator' ) {
			// Resolve Site ID — priority: selected website > single available site > manual fallback
			$selected_website = get_option( 'bol_affiliate_insights_selected_website', 'all_sites' );
			$available_sites  = array();
			$resolved_site_id = '';
			$site_source      = '';

			if ( $selected_website !== 'all_sites' ) {
				$resolved_site_id = $selected_website;
				$site_source      = 'selected';
			} else {
				$sites_cache_key = 'bol_available_sites_' . date( 'Y-m-d' );
				$available_sites = get_transient( $sites_cache_key );
				if ( false === $available_sites ) {
					$fetched         = $api_client->get_available_sites();
					$available_sites = ( ! empty( $fetched ) && is_array( $fetched ) ) ? $fetched : array();
					set_transient( $sites_cache_key, $available_sites, DAY_IN_SECONDS );
				}

				if ( count( $available_sites ) === 1 ) {
					$resolved_site_id = (string) array_key_first( $available_sites );
					$site_source      = 'auto';
				} elseif ( count( $available_sites ) > 1 ) {
					$site_source = 'choose';
				} else {
					$resolved_site_id = trim( (string) get_option( 'tbmm_bol_site_id', '' ) );
					$site_source      = $resolved_site_id ? 'manual' : '';
				}
			}

			$settings_url = esc_url( admin_url( 'admin.php?page=tb-money-manager&tab=bol&subtab=settings' ) );
			?>
			<h3><?php esc_html_e( 'Bol.com Linkgenerator', 'tbmm' ); ?></h3>
			<p><?php esc_html_e( 'Plak een bol.com URL en genereer direct een schone affiliate-trackinglink.', 'tbmm' ); ?></p>

			<?php if ( $site_source === 'selected' ) : ?>
				<div class="notice notice-success inline" style="margin-bottom:16px;"><p>
					<?php
					/* translators: %s = site ID */
					printf( __( 'Site ID <strong>%s</strong> — afkomstig uit je geselecteerde website-instelling.', 'tbmm' ), esc_html( $resolved_site_id ) );
					?>
				</p></div>
			<?php elseif ( $site_source === 'auto' ) :
				$auto_name = reset( $available_sites ); ?>
				<div class="notice notice-success inline" style="margin-bottom:16px;"><p>
					<?php
					/* translators: 1: site ID, 2: site name */
					printf( __( 'Site ID <strong>%1$s</strong> — automatisch bepaald (%2$s, enige gekoppelde website).', 'tbmm' ), esc_html( $resolved_site_id ), esc_html( $auto_name ) );
					?>
				</p></div>
			<?php elseif ( $site_source === 'manual' ) : ?>
				<div class="notice notice-info inline" style="margin-bottom:16px;"><p>
					<?php
					/* translators: 1: site ID, 2: link to settings */
					printf( __( 'Site ID <strong>%1$s</strong> — handmatig ingesteld. Koppel de <a href="%2$s">Bol.com API</a> voor automatische detectie.', 'tbmm' ), esc_html( $resolved_site_id ), $settings_url );
					?>
				</p></div>
			<?php elseif ( $site_source === 'choose' ) : ?>
				<div class="notice notice-info inline" style="margin-bottom:16px;"><p>
					<?php
					/* translators: %s = link to settings page */
					printf( __( 'Je hebt meerdere gekoppelde websites. Kies hieronder welke je wilt gebruiken, of selecteer een specifieke website via <a href="%s">Instellingen</a>.', 'tbmm' ), $settings_url );
					?>
				</p></div>
			<?php else : ?>
				<div class="notice notice-warning inline" style="margin-bottom:16px;"><p>
					<?php
					/* translators: %s = link to settings page */
					printf( __( '<strong>Geen Site ID beschikbaar.</strong> <a href="%s">Koppel de Bol.com API</a> of vul het Site ID handmatig in via Instellingen.', 'tbmm' ), $settings_url );
					?>
				</p></div>
			<?php endif; ?>

			<div id="tbmm-link-generator" style="max-width:720px;">
				<table class="form-table" role="presentation" style="margin-bottom:0;">
					<?php if ( $site_source === 'choose' ) : ?>
					<tr>
						<th scope="row"><label for="lg-site"><?php esc_html_e( 'Website', 'tbmm' ); ?></label></th>
						<td>
							<select id="lg-site">
								<?php foreach ( $available_sites as $code => $name ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?> — Site ID: <?php echo esc_html( $code ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><label for="lg-url"><?php esc_html_e( 'Bol.com URL', 'tbmm' ); ?></label></th>
						<td><input type="url" id="lg-url" class="large-text" placeholder="<?php esc_attr_e( 'Plak hier de URL uit je browser...', 'tbmm' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Type', 'tbmm' ); ?></th>
						<td>
							<label style="margin-right:16px;"><input type="radio" name="lg-type" value="text" checked> <?php esc_html_e( 'Tekstlink', 'tbmm' ); ?></label>
							<label style="margin-right:16px;"><input type="radio" name="lg-type" value="html"> <?php esc_html_e( 'HTML-link', 'tbmm' ); ?></label>
							<label><input type="radio" name="lg-type" value="product"> <?php esc_html_e( 'Productlink', 'tbmm' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lg-name"><?php esc_html_e( 'Link naam', 'tbmm' ); ?></label></th>
						<td>
							<input type="text" id="lg-name" class="regular-text" placeholder="<?php esc_attr_e( 'Automatisch gegenereerd...', 'tbmm' ); ?>">
							<p class="description"><?php esc_html_e( 'Wordt gebruikt voor rapportage', 'tbmm' ); ?> (<code>name=</code>). <?php esc_html_e( 'Automatisch ingevuld — pas aan indien gewenst.', 'tbmm' ); ?></p>
						</td>
					</tr>
					<tr id="lg-anchor-row" style="display:none;">
						<th scope="row"><label for="lg-anchor"><?php esc_html_e( 'Ankertekst', 'tbmm' ); ?></label></th>
						<td><input type="text" id="lg-anchor" class="regular-text" placeholder="<?php esc_attr_e( 'Tekst die de bezoeker ziet...', 'tbmm' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="lg-subid"><?php esc_html_e( 'SubID', 'tbmm' ); ?></label></th>
						<td>
							<input type="text" id="lg-subid" class="regular-text" placeholder="<?php esc_attr_e( 'bijv. sidebar, review-pagina...', 'tbmm' ); ?>">
							<p class="description"><?php esc_html_e( 'Optioneel. Gebruik je voor tracking per plaatsing', 'tbmm' ); ?> (<code>subid=</code>).</p>
						</td>
					</tr>
				</table>

				<div id="lg-result" style="display:none;margin-top:20px;padding:16px;background:#fff;border:1px solid #c3c4c7;border-radius:3px;">
					<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
						<h4 style="margin:0;"><?php esc_html_e( 'Affiliate link', 'tbmm' ); ?></h4>
						<div style="display:flex;align-items:center;gap:10px;">
							<button type="button" id="lg-copy-btn" class="button button-primary">&#128203; <?php esc_html_e( 'Kopieer', 'tbmm' ); ?></button>
							<span id="lg-copy-confirm" style="color:green;display:none;font-size:13px;">✔ <?php esc_html_e( 'Gekopieerd!', 'tbmm' ); ?></span>
						</div>
					</div>
					<textarea id="lg-output" rows="3" class="large-text" readonly style="font-family:monospace;font-size:12px;background:#f9f9f9;resize:vertical;cursor:text;"></textarea>
					<div id="lg-preview" style="display:none;margin-top:10px;padding:8px 12px;background:#f0f6fc;border-left:3px solid #2271b1;border-radius:0 3px 3px 0;">
						<strong style="font-size:12px;color:#646970;">Preview:</strong><br>
						<span id="lg-preview-link" style="font-size:13px;"></span>
					</div>
					<div id="lg-clean-url-info" style="margin-top:8px;font-size:11px;color:#646970;"></div>
				</div>
				<div id="lg-invalid-notice" style="display:none;margin-top:16px;color:#d63638;font-size:13px;"></div>
			</div>

			<script>
			(function() {
				var resolvedSiteId = <?php echo wp_json_encode( $resolved_site_id ); ?>;
				var siteSource     = <?php echo wp_json_encode( $site_source ); ?>;

				function getActiveSiteId() {
					if ( siteSource === 'choose' ) {
						var sel = document.getElementById( 'lg-site' );
						return sel ? sel.value : '';
					}
					return resolvedSiteId;
				}

				function slugToTitle( slug ) {
					var parts = slug.replace( /\/$/, '' ).split( '/' ).filter( function( p ) {
						return p && !/^\d+$/.test( p );
					} );
					var last = parts[ parts.length - 1 ] || '';
					return last.split( '-' ).filter( Boolean ).slice( 0, 4 ).map( function( w ) {
						return w.charAt(0).toUpperCase() + w.slice(1);
					} ).join( ' ' );
				}

				function cleanBolUrl( rawUrl ) {
					try {
						var url = new URL( rawUrl );
						if ( ! url.hostname.includes( 'bol.com' ) ) return null;
						url.hostname = 'www.bol.com';
						var path = url.pathname;
						if ( path !== '/' && ! path.endsWith( '/' ) ) path += '/';
						if ( path.includes( '/s/' ) ) {
							var search = ( url.searchParams.get( 'searchtext' ) || '' ).replace( /\+/g, ' ' );
							if ( ! search ) return 'https://www.bol.com' + path;
							return 'https://www.bol.com' + path + '?searchtext=' + encodeURIComponent( search );
						}
						return 'https://www.bol.com' + path;
					} catch(e) { return null; }
				}

				function autoName( rawUrl ) {
					try {
						var url = new URL( rawUrl );
						var path = url.pathname;
						if ( path === '/' || path === '' ) return '';
						if ( path.includes( '/s/' ) ) {
							var s = ( url.searchParams.get( 'searchtext' ) || '' ).replace( /\+/g, ' ' ).trim();
							return s.split( ' ' ).filter( Boolean ).slice( 0, 4 ).map( function( w ) {
								return w.charAt(0).toUpperCase() + w.slice(1);
							} ).join( ' ' );
						}
						return slugToTitle( path );
					} catch(e) { return ''; }
				}

				function buildAffiliateUrl( siteId, cleanUrl, name, subId ) {
					var qs = 'p=2&t=url&s=' + encodeURIComponent( siteId )
						+ '&f=TXL&url=' + encodeURIComponent( cleanUrl )
						+ '&name=' + encodeURIComponent( name );
					if ( subId ) qs += '&subid=' + encodeURIComponent( subId );
					return 'https://partner.bol.com/click/click?' + qs;
				}

				var urlInput    = document.getElementById( 'lg-url' );
				var nameInput   = document.getElementById( 'lg-name' );
				var anchorInput = document.getElementById( 'lg-anchor' );
				var subidInput  = document.getElementById( 'lg-subid' );
				var resultDiv   = document.getElementById( 'lg-result' );
				var invalidDiv  = document.getElementById( 'lg-invalid-notice' );

				function render() {
					var siteId   = getActiveSiteId();
					var rawUrl   = urlInput.value.trim();
					var cleanUrl = rawUrl ? cleanBolUrl( rawUrl ) : null;

					resultDiv.style.display  = 'none';
					invalidDiv.style.display = 'none';

					if ( ! rawUrl ) return;

					if ( ! siteId ) {
						// TODO: Phase 3 — wp.i18n voor JS-vertalingen
						invalidDiv.textContent = 'Geen Site ID beschikbaar — ga naar Instellingen om de Bol.com API te koppelen.';
						invalidDiv.style.display = 'block';
						return;
					}

					if ( ! cleanUrl ) {
						invalidDiv.textContent = 'Dit lijkt geen geldige bol.com URL te zijn.';
						invalidDiv.style.display = 'block';
						return;
					}

					var name   = nameInput.value.trim();
					var subId  = subidInput.value.trim();
					var type   = document.querySelector( 'input[name="lg-type"]:checked' ).value;
					var anchor = anchorInput.value.trim() || name || 'Bekijk op bol.com';
					var affUrl = buildAffiliateUrl( siteId, cleanUrl, name, subId );
					var output;

					if ( type === 'html' ) {
						output = '<a href="' + affUrl + '">' + anchor + '</a>';
						document.getElementById( 'lg-preview-link' ).innerHTML =
							'<a href="' + affUrl + '" target="_blank" rel="noopener noreferrer">' + anchor + '</a>';
						document.getElementById( 'lg-preview' ).style.display = '';
					} else {
						output = affUrl;
						document.getElementById( 'lg-preview' ).style.display = 'none';
					}

					document.getElementById( 'lg-output' ).value = output;
					document.getElementById( 'lg-clean-url-info' ).textContent =
						'Opgeschoonde doel-URL: ' + cleanUrl + ' · Site ID: ' + siteId;
					document.getElementById( 'lg-copy-confirm' ).style.display = 'none';
					resultDiv.style.display = '';
				}

				urlInput.addEventListener( 'input', function() {
					if ( ! nameInput.dataset.manuallyEdited ) {
						var name = autoName( this.value );
						nameInput.value = name;
						if ( ! anchorInput.dataset.manuallyEdited ) anchorInput.value = name;
					}
					render();
				} );

				nameInput.addEventListener( 'input', function() {
					this.dataset.manuallyEdited = '1';
					render();
				} );

				anchorInput.addEventListener( 'input', function() {
					this.dataset.manuallyEdited = '1';
					render();
				} );

				subidInput.addEventListener( 'input', render );

				document.querySelectorAll( 'input[name="lg-type"]' ).forEach( function( radio ) {
					radio.addEventListener( 'change', function() {
						document.getElementById( 'lg-anchor-row' ).style.display = ( this.value === 'html' ) ? '' : 'none';
						render();
					} );
				} );

				var siteSelect = document.getElementById( 'lg-site' );
				if ( siteSelect ) siteSelect.addEventListener( 'change', render );

				document.getElementById( 'lg-copy-btn' ).addEventListener( 'click', function() {
					var ta = document.getElementById( 'lg-output' );
					ta.select();
					ta.setSelectionRange( 0, 99999 );
					try {
						document.execCommand( 'copy' );
					} catch(e) {
						navigator.clipboard && navigator.clipboard.writeText( ta.value );
					}
					var confirm = document.getElementById( 'lg-copy-confirm' );
					confirm.style.display = 'inline';
					setTimeout( function() { confirm.style.display = 'none'; }, 2500 );
				} );
			})();
			</script>
			<?php

		} elseif ( $active_tab === 'settings' ) {
			?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'bol_affiliate_insights_options_group' );
				do_settings_sections( 'bol-affiliate-insights-settings' );
				submit_button( __( 'Instellingen opslaan', 'tbmm' ) );
				?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'API verbindingen testen', 'tbmm' ); ?></h2>
			<p><?php esc_html_e( 'Test of de ingevoerde credentials een geldig access token kunnen ophalen bij bol.com.', 'tbmm' ); ?></p>
			<div style="display:flex;gap:16px;margin-top:12px;margin-bottom:4px;">
				<div style="flex:1;border:1px solid #c3c4c7;border-radius:3px;padding:16px 20px;">
					<h3 style="margin:0 0 4px;"><?php esc_html_e( 'Reporting API', 'tbmm' ); ?></h3>
					<p style="margin:0 0 12px;color:#646970;font-size:13px;"><?php esc_html_e( 'Gebruikt voor orders, commissies en promotie-rapporten. Credentials staan onder', 'tbmm' ); ?> <em><?php esc_html_e( 'API Credentials', 'tbmm' ); ?></em> <?php esc_html_e( 'hierboven.', 'tbmm' ); ?></p>
					<button type="button" id="bol-test-connection-button" class="button"><?php esc_html_e( 'Verbinding testen', 'tbmm' ); ?></button>
					<div id="bol-test-connection-results" style="margin-top:10px;font-size:13px;"></div>
				</div>
				<div style="flex:1;border:1px solid #c3c4c7;border-radius:3px;padding:16px 20px;">
					<h3 style="margin:0 0 4px;"><?php esc_html_e( 'Marketing Catalog API', 'tbmm' ); ?></h3>
					<p style="margin:0 0 12px;color:#646970;font-size:13px;"><?php esc_html_e( 'Gebruikt voor productdata op EAN (prijs, afbeeldingen, ratings). Credentials staan onder', 'tbmm' ); ?> <em><?php esc_html_e( 'Marketing Catalog API', 'tbmm' ); ?></em> <?php esc_html_e( 'hierboven.', 'tbmm' ); ?></p>
					<button type="button" id="bol-test-marketing-connection-button" class="button"><?php esc_html_e( 'Verbinding testen', 'tbmm' ); ?></button>
					<div id="bol-test-marketing-connection-results" style="margin-top:10px;font-size:13px;"></div>
				</div>
			</div>
			<hr>
			<h2><?php esc_html_e( 'Cache', 'tbmm' ); ?></h2>
			<p><?php esc_html_e( 'API-data wordt tot 1 uur gecached. Gebruik deze knop om de cache te legen en verse data op te halen bij de volgende paginabezoek.', 'tbmm' ); ?></p>
			<button type="button" id="bol-clear-cache-button" class="button button-secondary"><?php esc_html_e( 'Cache legen', 'tbmm' ); ?></button>
			<span id="bol-clear-cache-result" style="margin-left:10px;"></span>
			<hr>
			<h2><?php esc_html_e( 'API credentials aanvragen', 'tbmm' ); ?></h2>
			<p>
				<?php
				/* translators: %s = link to bol.com partner platform */
				printf( __( 'Log in op het %s, ga naar <strong>Account → Open API</strong> en voeg de gewenste API-toegang toe. Je vindt daar Client ID en Client Secret per API-koppeling.', 'tbmm' ),
					'<a href="https://partnerplatform.bol.com" target="_blank" rel="noopener">' . esc_html__( 'bol.com partnerplatform', 'tbmm' ) . '</a>'
				);
				?>
			</p>
			<?php
		}
	}
}
