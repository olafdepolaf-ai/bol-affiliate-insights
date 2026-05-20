<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Bol\Service\ApiAuthService;
use TuinenBalkon\TBMoneyManager\Bol\Service\ApiClient;
use TuinenBalkon\TBMoneyManager\Bol\Service\ReportDataService;
use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class DashboardWidget {

	const CACHE_KEY = 'tbmm_dashboard_widget';
	const CACHE_TTL = 3600;

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'register' ] );
	}

	public function register(): void {
		wp_add_dashboard_widget(
			'tbmm_earnings_widget',
			'TB Money Manager — Verdiensten laatste 30 dagen',
			[ $this, 'render' ]
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
		$end   = new \DateTimeImmutable( 'today', wp_timezone() );
		$start = $end->modify( '-29 days' );

		return [
			'bol'          => $this->fetch_bol( $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ),
			'tt'           => $this->fetch_tt( $start ),
			'generated_at' => current_time( 'H:i' ),
		];
	}

	private function fetch_bol( string $start, string $end ): array {
		$credentials = get_option( 'bol_affiliate_insights_credentials', [] );
		if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
			return [ 'configured' => false ];
		}

		$client = new ApiClient( new ApiAuthService() );

		// Orders + commissie
		$orders_data  = $client->get_orders_report( $start, $end );
		$order_count  = 0;
		$commission   = 0.0;

		if ( ! is_wp_error( $orders_data ) && ! empty( $orders_data['items'] ) ) {
			$order_count = count( $orders_data['items'] );
			foreach ( $orders_data['items'] as $item ) {
				$commission += (float) ( $item['commission'] ?? 0 );
			}
		}

		// Saldo (verwachte uitkering) — hergebruik bestaande cache indien aanwezig
		$saldo = get_transient( 'bol_saldo_metrics' );
		if ( false === $saldo ) {
			$saldo = ( new ReportDataService( $client ) )->get_saldo_metrics();
			set_transient( 'bol_saldo_metrics', $saldo, HOUR_IN_SECONDS );
		}

		return [
			'configured'     => true,
			'orders'         => $order_count,
			'commission'     => $commission,
			'saldo_approved' => (float) ( $saldo['approved'] ?? 0 ),
			'saldo_pending'  => (float) ( $saldo['pending']  ?? 0 ),
			'error'          => is_wp_error( $orders_data ) ? $orders_data->get_error_message() : null,
		];
	}

	private function fetch_tt( \DateTimeImmutable $since ): array {
		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );

		if ( empty( $customer_id ) || empty( $access_key ) ) {
			return [ 'configured' => false ];
		}

		$tt      = new TradeTrackerService();
		$site_id = $tt->get_primary_site_id();

		if ( is_wp_error( $site_id ) ) {
			return [ 'configured' => true, 'error' => $site_id->get_error_message() ];
		}

		$cutoff      = $since->format( 'Y-m-d' );
		$current_year = (int) gmdate( 'Y' );
		$commission  = 0.0;
		$count       = 0;

		$years = [ $current_year ];
		// Include previous year if 30-day window crosses Jan 1
		if ( $since->format( 'Y' ) < gmdate( 'Y' ) ) {
			$years[] = $current_year - 1;
		}

		foreach ( $years as $year ) {
			$sales = $tt->get_sales_year( $site_id, $year );
			if ( is_wp_error( $sales ) ) {
				return [ 'configured' => true, 'error' => $sales->get_error_message() ];
			}
			foreach ( $sales as $t ) {
				$t = is_object( $t ) ? $t : (object) $t;
				$reg_date = isset( $t->registrationDate )
					? substr( (string) $t->registrationDate, 0, 10 )
					: '';
				if ( $reg_date >= $cutoff ) {
					$commission += (float) ( $t->commission ?? 0 );
					$count++;
				}
			}
		}

		return [
			'configured' => true,
			'commission' => $commission,
			'count'      => $count,
			'error'      => null,
		];
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	private function render_html( array $data ): void {
		$bol = $data['bol'] ?? [];
		$tt  = $data['tt']  ?? [];
		?>
		<style>
			#tbmm_earnings_widget .tbmm-section { margin-bottom: 16px; }
			#tbmm_earnings_widget .tbmm-section-title {
				font-size: 12px; font-weight: 600; text-transform: uppercase;
				letter-spacing: .05em; color: #646970; margin: 0 0 8px;
			}
			#tbmm_earnings_widget .tbmm-rows { display: flex; flex-direction: column; gap: 4px; }
			#tbmm_earnings_widget .tbmm-row {
				display: flex; justify-content: space-between; align-items: baseline;
				font-size: 13px; color: #1d2327;
			}
			#tbmm_earnings_widget .tbmm-row .tbmm-label { color: #646970; }
			#tbmm_earnings_widget .tbmm-row .tbmm-value { font-weight: 600; font-variant-numeric: tabular-nums; }
			#tbmm_earnings_widget .tbmm-row .tbmm-value.tbmm-big { font-size: 15px; color: #00a32a; }
			#tbmm_earnings_widget .tbmm-divider { border: none; border-top: 1px solid #f0f0f1; margin: 12px 0; }
			#tbmm_earnings_widget .tbmm-not-configured {
				font-size: 12px; color: #949494; font-style: italic;
			}
			#tbmm_earnings_widget .tbmm-error { font-size: 12px; color: #b32d2e; }
			#tbmm_earnings_widget .tbmm-footer {
				margin-top: 12px; font-size: 11px; color: #949494;
				display: flex; justify-content: space-between; align-items: center;
			}
			#tbmm_earnings_widget .tbmm-footer a { color: #949494; text-decoration: none; }
			#tbmm_earnings_widget .tbmm-footer a:hover { color: #2271b1; }
		</style>

		<?php /* ---- Bol.com ---- */ ?>
		<div class="tbmm-section">
			<p class="tbmm-section-title">🟠 Bol.com</p>
			<?php if ( empty( $bol['configured'] ) ) : ?>
				<p class="tbmm-not-configured">
					Nog niet geconfigureerd.
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tb-money-manager&tab=bol&subtab=settings' ) ); ?>">Instellingen →</a>
				</p>
			<?php elseif ( ! empty( $bol['error'] ) ) : ?>
				<p class="tbmm-error"><?php echo esc_html( $bol['error'] ); ?></p>
			<?php else : ?>
				<div class="tbmm-rows">
					<div class="tbmm-row">
						<span class="tbmm-label">Commissie (30 dgn)</span>
						<span class="tbmm-value tbmm-big"><?php echo esc_html( $this->fmt( $bol['commission'] ) ); ?></span>
					</div>
					<div class="tbmm-row">
						<span class="tbmm-label">Orders</span>
						<span class="tbmm-value"><?php echo esc_html( (int) $bol['orders'] ); ?></span>
					</div>
					<div class="tbmm-row">
						<span class="tbmm-label">Goedgekeurd saldo</span>
						<span class="tbmm-value"><?php echo esc_html( $this->fmt( $bol['saldo_approved'] ) ); ?></span>
					</div>
					<div class="tbmm-row">
						<span class="tbmm-label">Open saldo</span>
						<span class="tbmm-value"><?php echo esc_html( $this->fmt( $bol['saldo_pending'] ) ); ?></span>
					</div>
					<div class="tbmm-row" style="margin-top:2px; border-top:1px dashed #e0e0e0; padding-top:4px;">
						<span class="tbmm-label">Verwachte uitkering</span>
						<span class="tbmm-value" style="color:#2271b1;"><?php echo esc_html( $this->fmt( $bol['saldo_approved'] + $bol['saldo_pending'] ) ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<hr class="tbmm-divider">

		<?php /* ---- TradeTracker ---- */ ?>
		<div class="tbmm-section">
			<p class="tbmm-section-title">🔵 TradeTracker</p>
			<?php if ( empty( $tt['configured'] ) ) : ?>
				<p class="tbmm-not-configured">
					Nog niet geconfigureerd.
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker' ) ); ?>">Instellingen →</a>
				</p>
			<?php elseif ( ! empty( $tt['error'] ) ) : ?>
				<p class="tbmm-error"><?php echo esc_html( $tt['error'] ); ?></p>
			<?php else : ?>
				<div class="tbmm-rows">
					<div class="tbmm-row">
						<span class="tbmm-label">Commissie (30 dgn)</span>
						<span class="tbmm-value tbmm-big"><?php echo esc_html( $this->fmt( $tt['commission'] ) ); ?></span>
					</div>
					<div class="tbmm-row">
						<span class="tbmm-label">Transacties</span>
						<span class="tbmm-value"><?php echo esc_html( (int) $tt['count'] ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="tbmm-footer">
			<span>Bijgewerkt om <?php echo esc_html( $data['generated_at'] ?? '—' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tb-money-manager' ) ); ?>">Volledig rapport →</a>
		</div>
		<?php
	}

	private function fmt( float $amount ): string {
		return '€ ' . number_format( $amount, 2, ',', '.' );
	}
}
