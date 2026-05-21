<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Bol\Service\ApiAuthService;
use TuinenBalkon\TBMoneyManager\Bol\Service\ApiClient;
use TuinenBalkon\TBMoneyManager\Bol\Service\ReportDataService;
use TuinenBalkon\TBMoneyManager\Google\SiteKitBridge;
use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class DashboardWidget {

	const CACHE_KEY = 'tbmm_dashboard_widget';
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

		$periods = [
			'today' => [ $today, $today ],
			'last7' => [ $d7,    $today ],
			'last30'=> [ $d30,   $today ],
		];

		$result = [ 'configured' => true ];

		foreach ( $periods as $key => [$start, $end] ) {
			$data       = $client->get_orders_report( $start, $end );
			$orders     = 0;
			$commission = 0.0;
			if ( ! is_wp_error( $data ) && ! empty( $data['items'] ) ) {
				$orders     = count( $data['items'] );
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
			return [ 'configured' => true, 'error' => true ];
		}

		$today      = gmdate( 'Y-m-d' );
		$d7_cutoff  = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
		$d30_cutoff = gmdate( 'Y-m-d', strtotime( '-29 days' ) );

		$current_year = (int) gmdate( 'Y' );
		$years = [ $current_year ];
		if ( (int) gmdate( 'Y', strtotime( '-29 days' ) ) < $current_year ) {
			$years[] = $current_year - 1;
		}

		$buckets = [ 'today' => [0.0, 0], 'last7' => [0.0, 0], 'last30' => [0.0, 0] ];

		foreach ( $years as $year ) {
			$sales = $tt->get_sales_year( $site_id, $year );
			if ( is_wp_error( $sales ) ) {
				return [ 'configured' => true, 'error' => true ];
			}
			foreach ( $sales as $t ) {
				$t   = is_object( $t ) ? $t : (object) $t;
				$date = isset( $t->registrationDate ) ? substr( (string) $t->registrationDate, 0, 10 ) : '';
				$com  = (float) ( $t->commission ?? 0 );
				if ( $date === $today ) {
					$buckets['today'][0] += $com;
					$buckets['today'][1]++;
				}
				if ( $date >= $d7_cutoff ) {
					$buckets['last7'][0] += $com;
					$buckets['last7'][1]++;
				}
				if ( $date >= $d30_cutoff ) {
					$buckets['last30'][0] += $com;
					$buckets['last30'][1]++;
				}
			}
		}

		return [
			'configured' => true,
			'today'      => [ 'commission' => $buckets['today'][0], 'count' => $buckets['today'][1] ],
			'last7'      => [ 'commission' => $buckets['last7'][0], 'count' => $buckets['last7'][1] ],
			'last30'     => [ 'commission' => $buckets['last30'][0], 'count' => $buckets['last30'][1] ],
		];
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

		$has_bol     = ! empty( $bol['configured'] ) && empty( $bol['error'] );
		$has_tt      = ! empty( $tt['configured'] ) && empty( $tt['error'] );
		$has_adsense = ! empty( $adsense['available'] );

		if ( ! $has_bol && ! $has_tt && ! $has_adsense ) {
			echo '<p style="color:#949494; font-size:12px; font-style:italic;">Geen bronnen geconfigureerd. Stel Bol.com, TradeTracker of AdSense (via Site Kit) in.</p>';
			return;
		}
		?>
		<style>
			#tbmm_earnings_widget .tbmm-period { margin-bottom:14px; }
			#tbmm_earnings_widget .tbmm-period-title {
				font-size:11px; font-weight:700; text-transform:uppercase;
				letter-spacing:.06em; color:#646970; margin:0 0 6px;
			}
			#tbmm_earnings_widget .tbmm-rows { display:flex; flex-direction:column; gap:3px; }
			#tbmm_earnings_widget .tbmm-row {
				display:flex; justify-content:space-between; align-items:baseline;
				font-size:13px;
			}
			#tbmm_earnings_widget .tbmm-label { color:#646970; }
			#tbmm_earnings_widget .tbmm-value { font-weight:600; font-variant-numeric:tabular-nums; }
			#tbmm_earnings_widget .tbmm-value.green { color:#00a32a; }
			#tbmm_earnings_widget .tbmm-delta {
				font-size:11px; margin-left:5px; font-weight:400;
			}
			#tbmm_earnings_widget .tbmm-delta.up   { color:#00a32a; }
			#tbmm_earnings_widget .tbmm-delta.down  { color:#b32d2e; }
			#tbmm_earnings_widget .tbmm-divider {
				border:none; border-top:1px solid #f0f0f1; margin:10px 0;
			}
			#tbmm_earnings_widget .tbmm-saldo {
				margin-top:6px; padding-top:6px; border-top:1px dashed #e0e0e0;
				font-size:12px; color:#646970;
			}
			#tbmm_earnings_widget .tbmm-footer {
				margin-top:10px; font-size:11px; color:#949494;
				display:flex; justify-content:space-between; align-items:center;
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
			$has_any_data = false;
			ob_start();
			?>
			<div class="tbmm-rows">
				<?php if ( $has_bol ) : ?>
					<?php
					$orders = (int) ( $bol[ $period_key ]['orders'] ?? 0 );
					$com    = (float) ( $bol[ $period_key ]['commission'] ?? 0 );
					if ( $orders > 0 || $period_key !== 'today' ) :
						$has_any_data = true;
						?>
						<div class="tbmm-row">
							<span class="tbmm-label">🟠 Bol.com</span>
							<span class="tbmm-value green">
								<?php
								$parts = [];
								if ( $orders > 0 ) {
									$parts[] = $orders . ' ' . _n( 'order', 'orders', $orders );
								}
								$parts[] = $this->fmt( $com );
								echo esc_html( implode( ' · ', $parts ) );
								?>
							</span>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $has_tt ) : ?>
					<?php
					$t_com   = (float) ( $tt[ $period_key ]['commission'] ?? 0 );
					$t_count = (int)   ( $tt[ $period_key ]['count']      ?? 0 );
					if ( $t_count > 0 || $period_key !== 'today' ) :
						$has_any_data = true;
						?>
						<div class="tbmm-row">
							<span class="tbmm-label">🔵 TradeTracker</span>
							<span class="tbmm-value green">
								<?php
								$parts = [];
								if ( $t_count > 0 ) {
									$parts[] = $t_count . ' ' . _n( 'sale', 'sales', $t_count );
								}
								$parts[] = $this->fmt( $t_com );
								echo esc_html( implode( ' · ', $parts ) );
								?>
							</span>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $has_adsense ) : ?>
					<?php
					if ( $period_key === 'today' ) {
						$a_val = $adsense['today'] ?? null;
						$show  = $a_val !== null;
					} else {
						$a_val = (float) ( $adsense[ $period_key ] ?? 0 );
						$show  = true;
					}
					if ( $show ) :
						$has_any_data = true;
						?>
						<div class="tbmm-row">
							<span class="tbmm-label">🟡 AdSense</span>
							<span class="tbmm-value green">
								<?php echo esc_html( $this->fmt( (float) $a_val ) ); ?>
								<?php if ( $period_key === 'last7' && ! empty( $adsense['prev7'] ) && $adsense['prev7'] > 0 ) : ?>
									<?php
									$delta = $a_val - $adsense['prev7'];
									$pct   = round( $delta / $adsense['prev7'] * 100 );
									$cls   = $delta >= 0 ? 'up' : 'down';
									$sign  = $delta >= 0 ? '+' : '';
									?>
									<span class="tbmm-delta <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $sign . $pct . '%' ); ?></span>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php
			$rows_html = ob_get_clean();

			if ( $has_any_data ) :
				if ( ! $first ) echo '<hr class="tbmm-divider">';
				$first = false;
				?>
				<div class="tbmm-period">
					<p class="tbmm-period-title"><?php echo esc_html( $period_label ); ?></p>
					<?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php if ( $period_key === 'last30' && $has_bol ) : ?>
						<div class="tbmm-saldo">
							Saldo: <strong><?php echo esc_html( $this->fmt( $bol['saldo_approved'] ?? 0 ) ); ?></strong> goedgekeurd
							· <?php echo esc_html( $this->fmt( $bol['saldo_pending'] ?? 0 ) ); ?> open
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>

		<div class="tbmm-footer">
			<span>Bijgewerkt om <?php echo esc_html( $data['generated_at'] ?? '—' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tb-money-manager' ) ); ?>">Volledig rapport →</a>
		</div>
		<?php
	}

	private function fmt( float $amount ): string {
		return '€ ' . number_format( $amount, 2, ',', '.' );
	}
}
