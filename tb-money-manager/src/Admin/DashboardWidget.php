<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Bol\Service\ApiAuthService;
use TuinenBalkon\TBMoneyManager\Bol\Service\ApiClient;
use TuinenBalkon\TBMoneyManager\Bol\Service\ReportDataService;
use TuinenBalkon\TBMoneyManager\Google\SiteKitBridge;
use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class DashboardWidget {

	// Bump this suffix when the cached data structure changes to avoid stale cache reads.
	const CACHE_KEY = 'tbmm_dashboard_widget_v3';
	const CACHE_TTL = 3600;

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'register' ] );
	}

	public function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'tbmm_earnings_widget',
			'TB Money Manager — Verdiensten',
			[ $this, 'render' ],
			null,
			null,
			'side',
			'high'
		);
	}

	public function render(): void {
		// Allow manual cache flush via ?tbmm_flush_widget=1.
		if ( isset( $_GET['tbmm_flush_widget'] ) ) {
			delete_transient( self::CACHE_KEY );
		}

		$data = get_transient( self::CACHE_KEY );
		if ( false === $data ) {
			$data = $this->fetch_all();
			set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		}
		$this->render_html( $data );
	}

	// -------------------------------------------------------------------------
	// Data fetching
	// -------------------------------------------------------------------------

	private function fetch_all(): array {
		return [
			'bol'          => $this->fetch_bol(),
			'tt'           => $this->fetch_tt(),
			'adsense'      => $this->fetch_adsense(),
			'generated_at' => current_time( 'H:i' ),
		];
	}

	private function fetch_bol(): array {
		$credentials = get_option( 'bol_affiliate_insights_credentials', [] );
		if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
			return [ 'configured' => false ];
		}

		$client = new ApiClient( new ApiAuthService() );
		$today  = gmdate( 'Y-m-d' );
		$d7     = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
		$d30    = gmdate( 'Y-m-d', strtotime( '-29 days' ) );

		$result = [ 'configured' => true ];

		foreach ( [
			'today' => [ $today, $today ],
			'last7' => [ $d7,    $today ],
			'last30'=> [ $d30,   $today ],
		] as $key => [ $start, $end ] ) {
			$data       = $client->get_orders_report( $start, $end );
			$orders     = 0;
			$commission = 0.0;
			if ( ! is_wp_error( $data ) && ! empty( $data['items'] ) ) {
				$orders = count( $data['items'] );
				foreach ( $data['items'] as $item ) {
					$commission += (float) ( $item['commission'] ?? 0 );
				}
			}
			$result[ $key ] = [ 'orders' => $orders, 'commission' => $commission ];
		}

		$saldo = get_transient( 'bol_saldo_metrics' );
		if ( false === $saldo ) {
			$saldo = ( new ReportDataService( $client ) )->get_saldo_metrics();
			set_transient( 'bol_saldo_metrics', $saldo, HOUR_IN_SECONDS );
		}
		$result['saldo_approved'] = (float) ( $saldo['approved'] ?? 0 );
		$result['saldo_pending']  = (float) ( $saldo['pending']  ?? 0 );

		return $result;
	}

	private function fetch_tt(): array {
		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );

		if ( empty( $customer_id ) || empty( $access_key ) ) {
			return [ 'configured' => false ];
		}

		$tt      = new TradeTrackerService();
		$site_id = $tt->get_primary_site_id();

		if ( is_wp_error( $site_id ) ) {
			return [ 'configured' => false ];
		}

		$today      = gmdate( 'Y-m-d' );
		$d7_cutoff  = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
		$d30_cutoff = gmdate( 'Y-m-d', strtotime( '-29 days' ) );

		$current_year = (int) gmdate( 'Y' );
		$years = [ $current_year ];
		if ( (int) gmdate( 'Y', strtotime( '-29 days' ) ) < $current_year ) {
			$years[] = $current_year - 1;
		}

		$buckets = [
			'today' => [ 'commission' => 0.0, 'count' => 0 ],
			'last7' => [ 'commission' => 0.0, 'count' => 0 ],
			'last30'=> [ 'commission' => 0.0, 'count' => 0 ],
		];

		foreach ( $years as $year ) {
			$sales = $tt->get_sales_year( $site_id, $year );
			if ( is_wp_error( $sales ) ) {
				return [ 'configured' => false ];
			}
			foreach ( $sales as $t ) {
				$t    = is_object( $t ) ? $t : (object) $t;
				$date = isset( $t->registrationDate ) ? substr( (string) $t->registrationDate, 0, 10 ) : '';
				$com  = (float) ( $t->commission ?? 0 );
				if ( $date === $today ) {
					$buckets['today']['commission'] += $com;
					$buckets['today']['count']++;
				}
				if ( $date >= $d7_cutoff ) {
					$buckets['last7']['commission'] += $com;
					$buckets['last7']['count']++;
				}
				if ( $date >= $d30_cutoff ) {
					$buckets['last30']['commission'] += $com;
					$buckets['last30']['count']++;
				}
			}
		}

		return [ 'configured' => true ] + $buckets;
	}

	private function fetch_adsense(): array {
		$bridge = new SiteKitBridge();
		if ( ! $bridge->is_adsense_connected() ) {
			return [ 'available' => false ];
		}

		$daily = $bridge->get_adsense_daily_earnings();
		if ( is_wp_error( $daily ) || empty( $daily ) ) {
			return [ 'available' => false ];
		}

		return [
			'available' => true,
			'today'     => $daily[ gmdate( 'Y-m-d' ) ] ?? null,
			'last7'     => $this->sum_days( $daily, strtotime( '-6 days' ),  strtotime( 'today' ) ),
			'prev7'     => $this->sum_days( $daily, strtotime( '-13 days' ), strtotime( '-7 days' ) ),
			'last30'    => $this->sum_days( $daily, strtotime( '-29 days' ), strtotime( 'today' ) ),
		];
	}

	private function sum_days( array $daily, int $from_ts, int $to_ts ): float {
		$total = 0.0;
		for ( $ts = $from_ts; $ts <= $to_ts; $ts += DAY_IN_SECONDS ) {
			$total += $daily[ gmdate( 'Y-m-d', $ts ) ] ?? 0.0;
		}
		return $total;
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	private function render_html( array $data ): void {
		$bol     = $data['bol']     ?? [];
		$tt      = $data['tt']      ?? [];
		$adsense = $data['adsense'] ?? [];

		$has_bol     = ! empty( $bol['configured'] );
		$has_tt      = ! empty( $tt['configured'] );
		$has_adsense = ! empty( $adsense['available'] );

		if ( ! $has_bol && ! $has_tt && ! $has_adsense ) {
			echo '<p style="color:#949494;font-size:12px;font-style:italic;">Geen bronnen geconfigureerd.</p>';
			return;
		}
		?>
		<style>
			#tbmm_earnings_widget .tbmm-section { margin-bottom:12px; }
			#tbmm_earnings_widget .tbmm-section-title {
				font-size:10px; font-weight:700; text-transform:uppercase;
				letter-spacing:.07em; color:#646970; margin:0 0 5px; padding:0;
			}
			#tbmm_earnings_widget .tbmm-rows { display:flex; flex-direction:column; gap:2px; }
			#tbmm_earnings_widget .tbmm-row {
				display:flex; justify-content:space-between; align-items:baseline;
				font-size:12px;
			}
			#tbmm_earnings_widget .tbmm-lbl { color:#646970; white-space:nowrap; }
			#tbmm_earnings_widget .tbmm-val {
				font-weight:600; font-variant-numeric:tabular-nums;
				text-align:right; color:#1d2327;
			}
			#tbmm_earnings_widget .tbmm-val.has-value { color:#00a32a; }
			#tbmm_earnings_widget .tbmm-trend {
				font-size:10px; margin-left:4px; font-weight:400;
			}
			#tbmm_earnings_widget .tbmm-trend.up   { color:#00a32a; }
			#tbmm_earnings_widget .tbmm-trend.down  { color:#b32d2e; }
			#tbmm_earnings_widget .tbmm-divider { border:none; border-top:1px solid #f0f0f1; margin:8px 0; }
			#tbmm_earnings_widget .tbmm-uitbetaling {
				background:#f6f7f7; border:1px solid #e0e0e0; padding:8px 10px;
				margin-top:10px; font-size:12px;
			}
			#tbmm_earnings_widget .tbmm-uitbetaling-title {
				font-size:10px; font-weight:700; text-transform:uppercase;
				letter-spacing:.07em; color:#646970; margin:0 0 5px;
			}
			#tbmm_earnings_widget .tbmm-uitbetaling-total {
				font-size:14px; font-weight:700; color:#2271b1; margin-top:4px;
			}
			#tbmm_earnings_widget .tbmm-footer {
				margin-top:8px; font-size:11px; color:#949494;
				display:flex; justify-content:space-between;
			}
			#tbmm_earnings_widget .tbmm-footer a { color:#949494; text-decoration:none; }
			#tbmm_earnings_widget .tbmm-footer a:hover { color:#2271b1; }
		</style>

		<?php
		$periods = [
			'today'  => 'Vandaag',
			'last7'  => 'Laatste 7 dagen',
			'last30' => 'Laatste 30 dagen',
		];

		$first = true;
		foreach ( $periods as $period_key => $period_label ) :
			if ( ! $first ) echo '<hr class="tbmm-divider">';
			$first = false;
			?>
			<div class="tbmm-section">
				<p class="tbmm-section-title"><?php echo esc_html( $period_label ); ?></p>
				<div class="tbmm-rows">

					<?php if ( $has_bol ) :
						$orders = (int)   ( $bol[ $period_key ]['orders']     ?? 0 );
						$com    = (float) ( $bol[ $period_key ]['commission']  ?? 0 );
						$label  = $orders > 0
							? $orders . ' ' . _n( 'order', 'orders', $orders ) . ' · ' . $this->fmt( $com )
							: $this->fmt( $com );
						?>
						<div class="tbmm-row">
							<span class="tbmm-lbl">🟠 Bol.com</span>
							<span class="tbmm-val <?php echo $com > 0 ? 'has-value' : ''; ?>">
								<?php echo esc_html( $label ); ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( $has_tt ) :
						$t_count = (int)   ( $tt[ $period_key ]['count']      ?? 0 );
						$t_com   = (float) ( $tt[ $period_key ]['commission']  ?? 0 );
						$label   = $t_count > 0
							? $t_count . ' ' . _n( 'sale', 'sales', $t_count ) . ' · ' . $this->fmt( $t_com )
							: $this->fmt( $t_com );
						?>
						<div class="tbmm-row">
							<span class="tbmm-lbl">🔵 TradeTracker</span>
							<span class="tbmm-val <?php echo $t_com > 0 ? 'has-value' : ''; ?>">
								<?php echo esc_html( $label ); ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( $has_adsense ) :
						if ( $period_key === 'today' ) {
							$a_val = $adsense['today'] ?? null;
							$a_str = $a_val !== null ? $this->fmt( (float) $a_val ) : '—';
							$a_positive = $a_val !== null && $a_val > 0;
						} else {
							$a_val      = (float) ( $adsense[ $period_key ] ?? 0 );
							$a_str      = $this->fmt( $a_val );
							$a_positive = $a_val > 0;
						}

						$trend_html = '';
						if ( $period_key === 'last7' && ! empty( $adsense['prev7'] ) && $adsense['prev7'] > 0 && is_float( $a_val ) ) {
							$delta = $a_val - $adsense['prev7'];
							$pct   = round( $delta / $adsense['prev7'] * 100 );
							$cls   = $delta >= 0 ? 'up' : 'down';
							$arrow = $delta >= 0 ? '▲' : '▼';
							$trend_html = '<span class="tbmm-trend ' . $cls . '">' . esc_html( $arrow . ( $delta >= 0 ? '+' : '' ) . $pct . '%' ) . '</span>';
						}
						?>
						<div class="tbmm-row">
							<span class="tbmm-lbl">🟡 AdSense</span>
							<span class="tbmm-val <?php echo $a_positive ? 'has-value' : ''; ?>">
								<?php echo esc_html( $a_str ); ?><?php echo $trend_html; // phpcs:ignore ?>
							</span>
						</div>
					<?php endif; ?>

				</div>
			</div>
		<?php endforeach; ?>

		<?php
		// --- Uitbetaling sectie ---
		$show_bol_saldo     = $has_bol && ( ( $bol['saldo_approved'] ?? 0 ) + ( $bol['saldo_pending'] ?? 0 ) ) > 0;
		if ( $show_bol_saldo ) :
			$approved = (float) ( $bol['saldo_approved'] ?? 0 );
			$pending  = (float) ( $bol['saldo_pending']  ?? 0 );
			$total    = $approved + $pending;
			?>
			<hr class="tbmm-divider">
			<div class="tbmm-uitbetaling">
				<p class="tbmm-uitbetaling-title">Uitbetaling Bol.com</p>
				<div class="tbmm-rows">
					<div class="tbmm-row">
						<span class="tbmm-lbl">Goedgekeurd</span>
						<span class="tbmm-val"><?php echo esc_html( $this->fmt( $approved ) ); ?></span>
					</div>
					<div class="tbmm-row">
						<span class="tbmm-lbl">Open</span>
						<span class="tbmm-val"><?php echo esc_html( $this->fmt( $pending ) ); ?></span>
					</div>
				</div>
				<div class="tbmm-uitbetaling-total"><?php echo esc_html( $this->fmt( $total ) ); ?></div>
			</div>
		<?php endif; ?>

		<div class="tbmm-footer">
			<span>Bijgewerkt om <?php echo esc_html( $data['generated_at'] ?? '—' ); ?></span>
			<span>
				<a href="<?php echo esc_url( add_query_arg( 'tbmm_flush_widget', '1' ) ); ?>">↻</a>
				&nbsp;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tb-money-manager' ) ); ?>">Rapport →</a>
			</span>
		</div>
		<?php
	}

	private function fmt( float $amount ): string {
		return '€ ' . number_format( $amount, 2, ',', '.' );
	}
}
