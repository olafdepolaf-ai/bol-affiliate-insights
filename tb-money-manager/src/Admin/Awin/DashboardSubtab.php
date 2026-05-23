<?php

namespace TuinenBalkon\TBMoneyManager\Admin\Awin;

use TuinenBalkon\TBMoneyManager\Service\AwinService;

class DashboardSubtab {

	private AwinService $service;

	public function __construct( AwinService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		if ( ! $this->service->has_credentials() ) {
			echo '<p><em>' . esc_html__( 'Vul eerst de API-token en Publisher ID in via het tabblad Instellingen.', 'tbmm' ) . '</em></p>';
			return;
		}

		$current_year  = (int) gmdate( 'Y' );
		$selected_year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : $current_year;
		$selected_year = max( 2017, min( $current_year, $selected_year ) );

		// Refresh: cache wissen en herladen.
		if (
			isset( $_POST['tbmm_awin_refresh'] ) &&
			check_admin_referer( 'tbmm_awin_dashboard', 'tbmm_awin_nonce' )
		) {
			$this->service->clear_cache( $selected_year );
		}

		wp_enqueue_script( 'chart-js' );
		?>

		<form method="get" style="margin-bottom:16px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
			<input type="hidden" name="page" value="tb-money-manager" />
			<input type="hidden" name="tab" value="awin" />
			<input type="hidden" name="subtab" value="dashboard" />
			<label for="tbmm_awin_jaar" style="font-weight:600;"><?php esc_html_e( 'Jaar:', 'tbmm' ); ?></label>
			<select id="tbmm_awin_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2017; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<form method="post" style="margin-bottom:20px;">
			<?php wp_nonce_field( 'tbmm_awin_dashboard', 'tbmm_awin_nonce' ); ?>
			<input type="hidden" name="jaar" value="<?php echo esc_attr( $selected_year ); ?>" />
			<button type="submit" name="tbmm_awin_refresh" class="button">
				<?php esc_html_e( 'Data opnieuw ophalen', 'tbmm' ); ?>
			</button>
			<span style="margin-left:8px; color:#646970; font-size:12px;">
				<?php esc_html_e( 'Vervangt gecachede data voor dit jaar (inclusief verleden maanden).', 'tbmm' ); ?>
			</span>
		</form>

		<?php
		$transactions = $this->service->get_year_transactions( $selected_year );

		if ( is_wp_error( $transactions ) ) {
			echo '<div class="notice notice-error inline"><p><strong>'
				. esc_html__( 'Fout:', 'tbmm' )
				. '</strong> ' . esc_html( $transactions->get_error_message() ) . '</p></div>';
			return;
		}

		$this->render_kpi_cards( $transactions );
		$this->render_chart( $transactions, $selected_year );
		$this->render_transactions_table( $transactions );
	}

	private function render_kpi_cards( array $transactions ): void {
		$totals = [
			'pending'  => [ 'count' => 0, 'commission' => 0.0 ],
			'approved' => [ 'count' => 0, 'commission' => 0.0 ],
			'declined' => [ 'count' => 0, 'commission' => 0.0 ],
		];

		foreach ( $transactions as $tx ) {
			$t      = (object) $tx;
			$status = strtolower( $t->commissionStatus ?? $t->status ?? 'pending' );
			if ( ! isset( $totals[ $status ] ) ) {
				$totals[ $status ] = [ 'count' => 0, 'commission' => 0.0 ];
			}
			$totals[ $status ]['count']++;
			$totals[ $status ]['commission'] += (float) ( $t->commissionAmount ?? $t->commission ?? 0 );
		}

		$total_commission = array_sum( array_column( $totals, 'commission' ) );
		$total_count      = array_sum( array_column( $totals, 'count' ) );

		$labels = [
			'approved' => __( 'Goedgekeurd', 'tbmm' ),
			'pending'  => __( 'In behandeling', 'tbmm' ),
			'declined' => __( 'Afgekeurd', 'tbmm' ),
		];
		$colors = [ 'approved' => '#00a32a', 'pending' => '#dba617', 'declined' => '#d63638' ];
		?>
		<div class="alc-sales-summary">
			<div class="alc-sales-card alc-card-total">
				<div class="alc-card-num">
					<?php
					printf(
						/* translators: %d = number of transactions */
						esc_html__( '%d transacties', 'tbmm' ),
						$total_count
					);
					?>
				</div>
				<div class="alc-card-sub">€ <?php echo esc_html( number_format( $total_commission, 2, ',', '.' ) ); ?> <?php esc_html_e( 'totaal', 'tbmm' ); ?></div>
			</div>
			<?php foreach ( $labels as $key => $label ) :
				if ( empty( $totals[ $key ]['count'] ) ) continue;
			?>
			<div class="alc-sales-card">
				<div class="alc-card-num" style="color:<?php echo esc_attr( $colors[ $key ] ); ?>">
					<?php echo esc_html( $totals[ $key ]['count'] ); ?>
				</div>
				<div class="alc-card-sub">
					<?php echo esc_html( $label ); ?> —
					€ <?php echo esc_html( number_format( $totals[ $key ]['commission'], 2, ',', '.' ) ); ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function render_chart( array $transactions, int $year ): void {
		$current_year  = (int) gmdate( 'Y' );
		$current_month = ( $year === $current_year ) ? (int) gmdate( 'n' ) : 12;

		// Aggregeer per maand.
		$by_month = [];
		for ( $m = 1; $m <= $current_month; $m++ ) {
			$by_month[ $m ] = [ 'commission' => 0.0, 'sale' => 0.0, 'count' => 0 ];
		}
		foreach ( $transactions as $tx ) {
			$t     = (object) $tx;
			$date  = $t->transactionDate ?? $t->date ?? '';
			$month = $date ? (int) gmdate( 'n', strtotime( $date ) ) : 0;
			if ( $month < 1 || $month > 12 ) continue;
			$by_month[ $month ]['commission'] += (float) ( $t->commissionAmount ?? $t->commission ?? 0 );
			$by_month[ $month ]['sale']       += (float) ( $t->saleAmount ?? 0 );
			$by_month[ $month ]['count']++;
		}

		$month_names = [];
		for ( $m = 1; $m <= $current_month; $m++ ) {
			$month_names[] = date_i18n( 'M', mktime( 0, 0, 0, $m, 1 ) );
		}

		$chart_data = [
			'labels'     => $month_names,
			'commission' => array_values( array_column( $by_month, 'commission' ) ),
			'sale'       => array_values( array_column( $by_month, 'sale' ) ),
			'count'      => array_values( array_column( $by_month, 'count' ) ),
		];
		?>
		<div style="margin:24px 0 8px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
			<label for="tbmm-awin-metric" style="font-weight:600;"><?php esc_html_e( 'Metriek:', 'tbmm' ); ?></label>
			<select id="tbmm-awin-metric">
				<option value="commission"><?php esc_html_e( 'Commissie (€)', 'tbmm' ); ?></option>
				<option value="sale"><?php esc_html_e( 'Omzet (€)', 'tbmm' ); ?></option>
				<option value="count"><?php esc_html_e( 'Transacties', 'tbmm' ); ?></option>
			</select>
		</div>
		<div style="max-width:760px; margin-bottom:28px;">
			<canvas id="tbmmAwinChart" height="90"></canvas>
		</div>
		<script>
		(function() {
			var data  = <?php echo wp_json_encode( $chart_data ); ?>;
			var colors = { commission: '#2271b1', sale: '#00a32a', count: '#dba617' };
			var labels = {
				commission: '<?php echo esc_js( __( 'Commissie (€)', 'tbmm' ) ); ?>',
				sale:       '<?php echo esc_js( __( 'Omzet (€)', 'tbmm' ) ); ?>',
				count:      '<?php echo esc_js( __( 'Transacties', 'tbmm' ) ); ?>'
			};
			var chart;

			function buildChart(metric) {
				if (chart) chart.destroy();
				var ctx = document.getElementById('tbmmAwinChart').getContext('2d');
				chart = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: data.labels,
						datasets: [{
							label: labels[metric],
							data: data[metric],
							backgroundColor: colors[metric] + '99',
							borderColor: colors[metric],
							borderWidth: 1,
							borderRadius: 3
						}]
					},
					options: {
						responsive: true,
						plugins: { legend: { display: false } },
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									callback: function(v) {
										return metric === 'count' ? v : '€' + v.toFixed(2).replace('.', ',');
									}
								}
							}
						}
					}
				});
			}

			document.addEventListener('DOMContentLoaded', function() {
				buildChart('commission');
				document.getElementById('tbmm-awin-metric').addEventListener('change', function() {
					buildChart(this.value);
				});
			});
		})();
		</script>
		<?php
	}

	private function render_transactions_table( array $transactions ): void {
		if ( empty( $transactions ) ) {
			echo '<p><em>' . esc_html__( 'Geen transacties gevonden voor dit jaar.', 'tbmm' ) . '</em></p>';
			return;
		}

		usort( $transactions, function( $a, $b ) {
			$a = (object) $a;
			$b = (object) $b;
			$da = $a->transactionDate ?? $a->date ?? '';
			$db = $b->transactionDate ?? $b->date ?? '';
			return strcmp( $db, $da );
		} );

		$status_labels = [
			'approved' => __( 'Goedgekeurd', 'tbmm' ),
			'pending'  => __( 'In behandeling', 'tbmm' ),
			'declined' => __( 'Afgekeurd', 'tbmm' ),
		];
		$status_colors = [ 'approved' => '#00a32a', 'pending' => '#dba617', 'declined' => '#d63638' ];
		?>
		<table class="tbmm-table alc-sales-tbl" style="margin-top:20px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Datum', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'ID', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Adverteerder', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Referentie', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tbmm' ); ?></th>
					<th class="num"><?php esc_html_e( 'Orderbedr', 'tbmm' ); ?></th>
					<th class="num"><?php esc_html_e( 'Commissie', 'tbmm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $transactions as $tx ) :
					$t          = (object) $tx;
					$status_key = strtolower( $t->commissionStatus ?? $t->status ?? 'pending' );
					$color      = $status_colors[ $status_key ] ?? '#888';
					$label      = $status_labels[ $status_key ] ?? $status_key;

					$raw_date   = $t->transactionDate ?? $t->date ?? '';
					$tx_date    = $raw_date ? gmdate( 'd-m-Y H:i', strtotime( $raw_date ) ) : '—';
					$advertiser = $t->advertiserName ?? $t->advertiser ?? '—';
					$reference  = $t->publisherReference ?? $t->clickRef ?? $t->reference ?? '—';
					$sale_amt   = isset( $t->saleAmount )       ? '€ ' . number_format( (float) $t->saleAmount, 2, ',', '.' )       : '—';
					$commission = isset( $t->commissionAmount ) ? '€ ' . number_format( (float) $t->commissionAmount, 2, ',', '.' ) : '—';
				?>
				<tr>
					<td><?php echo esc_html( $tx_date ); ?></td>
					<td style="color:#646970; font-size:12px;"><?php echo esc_html( '#' . ( $t->id ?? $t->ID ?? '?' ) ); ?></td>
					<td><?php echo esc_html( $advertiser ); ?></td>
					<td><?php echo esc_html( $reference !== '' ? $reference : '—' ); ?></td>
					<td><span class="alc-badge" style="background:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $label ); ?></span></td>
					<td class="num"><?php echo esc_html( $sale_amt ); ?></td>
					<td class="num"><?php echo esc_html( $commission ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
