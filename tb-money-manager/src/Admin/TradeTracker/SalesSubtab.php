<?php

namespace TuinenBalkon\TBMoneyManager\Admin\TradeTracker;

use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class SalesSubtab {

	private TradeTrackerService $service;

	public function __construct( TradeTrackerService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		$current_year  = (int) gmdate( 'Y' );
		$selected_year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : $current_year;
		$selected_year = max( 2015, min( $current_year, $selected_year ) );

		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>' . esc_html__( 'Vul eerst de inloggegevens in via het tabblad Instellingen.', 'tbmm' ) . '</em></p>';
			return;
		}

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}
		?>

		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="tb-money-manager" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="sales" />
			<label for="tbmm_sales_jaar" style="font-weight:600;"><?php esc_html_e( 'Jaar:', 'tbmm' ); ?></label>
			<select id="tbmm_sales_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<?php
		$pending     = $this->service->get_pending_commission( $site_id );
		$last_pmt    = $this->service->get_last_payment();
		$has_payout  = ! is_wp_error( $pending ) || ! is_wp_error( $last_pmt );

		if ( $has_payout ) :
			$pend_amount = ( ! is_wp_error( $pending ) ) ? (float) $pending['commission'] : 0.0;
			$pend_count  = ( ! is_wp_error( $pending ) ) ? (int) $pending['count'] : 0;
			?>
			<div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:14px 18px; margin-bottom:20px; max-width:600px;">
				<h4 style="margin:0 0 10px; font-size:13px; color:#1d2327;"><?php esc_html_e( 'Uitbetaling', 'tbmm' ); ?></h4>
				<table style="border-collapse:collapse; width:100%; font-size:13px;">
					<tr>
						<td style="padding:4px 0; color:#646970; width:200px;"><?php esc_html_e( 'Openstaand', 'tbmm' ); ?></td>
						<td style="padding:4px 0; font-weight:600;">
							<?php echo esc_html( '€ ' . number_format( $pend_amount, 2, ',', '.' ) ); ?>
							<?php if ( $pend_count > 0 ) : ?>
								<span style="color:#646970; font-weight:normal; font-size:12px;">
									(<?php echo esc_html( $pend_count ); ?> <?php echo esc_html( _n( 'transactie', 'transacties', $pend_count, 'tbmm' ) ); ?>)
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( ! is_wp_error( $last_pmt ) && ! empty( $last_pmt ) ) : ?>
					<tr>
						<td style="padding:4px 0; color:#646970;"><?php esc_html_e( 'Laatste betaling', 'tbmm' ); ?></td>
						<td style="padding:4px 0;">
							€ <?php echo esc_html( number_format( (float) $last_pmt['amount'], 2, ',', '.' ) ); ?>
							<span style="color:#646970; font-size:12px;">
								<?php
								printf(
									/* translators: %s = date of payment */
									esc_html__( 'op %s', 'tbmm' ),
									esc_html( gmdate( 'd-m-Y', strtotime( $last_pmt['date'] ) ) )
								);
								?>
							</span>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ( $pend_amount > 0 && $pend_amount < 25 ) : ?>
					<tr>
						<td colspan="2" style="padding:6px 0 0; font-size:12px; color:#646970;">
							<?php
							printf(
								/* translators: %s = remaining amount until payout threshold */
								esc_html__( 'Minimumbedrag voor uitbetaling is € 25,00 — nog € %s te gaan.', 'tbmm' ),
								esc_html( number_format( 25 - $pend_amount, 2, ',', '.' ) )
							);
							?>
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
		<?php endif; ?>

		<?php
		$transactions = $this->service->get_sales_year( $site_id, $selected_year );

		if ( is_wp_error( $transactions ) ) {
			echo '<div class="notice notice-error inline"><p><strong>'
				. esc_html__( 'Fout:', 'tbmm' )
				. '</strong> ' . esc_html( $transactions->get_error_message() ) . '</p></div>';
			return;
		}

		if ( empty( $transactions ) ) {
			echo '<p><em>'
				. sprintf(
					/* translators: %s = year number */
					esc_html__( 'Geen sales gevonden voor %s.', 'tbmm' ),
					esc_html( $selected_year )
				  )
				. '</em></p>';
			return;
		}

		$summary = [
			'pending'  => [ 'count' => 0, 'commission' => 0.0 ],
			'accepted' => [ 'count' => 0, 'commission' => 0.0 ],
			'rejected' => [ 'count' => 0, 'commission' => 0.0 ],
		];
		foreach ( $transactions as $tx ) {
			$t      = (object) $tx;
			$status = strtolower( $t->transactionStatus ?? 'pending' );
			if ( ! isset( $summary[ $status ] ) ) {
				$summary[ $status ] = [ 'count' => 0, 'commission' => 0.0 ];
			}
			$summary[ $status ]['count']++;
			$summary[ $status ]['commission'] += (float) ( $t->commission ?? 0 );
		}

		$status_labels = [
			'accepted' => __( 'Geaccepteerd', 'tbmm' ),
			'pending'  => __( 'In behandeling', 'tbmm' ),
			'rejected' => __( 'Afgekeurd', 'tbmm' ),
		];
		$status_colors    = [ 'accepted' => '#00a32a', 'pending' => '#dba617', 'rejected' => '#d63638' ];
		$total_commission = array_sum( array_column( $summary, 'commission' ) );
		$total_count      = array_sum( array_column( $summary, 'count' ) );
		?>

		<div class="alc-sales-summary">
			<div class="alc-sales-card alc-card-total">
				<div class="alc-card-num">
					<?php
					printf(
						/* translators: %d = number of sales */
						esc_html__( '%d sales', 'tbmm' ),
						$total_count
					);
					?>
				</div>
				<div class="alc-card-sub">€ <?php echo esc_html( number_format( $total_commission, 2, ',', '.' ) ); ?> <?php esc_html_e( 'totaal', 'tbmm' ); ?></div>
			</div>
			<?php foreach ( $status_labels as $key => $label ) :
				if ( empty( $summary[ $key ]['count'] ) ) continue;
			?>
			<div class="alc-sales-card">
				<div class="alc-card-num" style="color:<?php echo esc_attr( $status_colors[ $key ] ); ?>">
					<?php echo esc_html( $summary[ $key ]['count'] ); ?>
				</div>
				<div class="alc-card-sub">
					<?php echo esc_html( $label ); ?> —
					€ <?php echo esc_html( number_format( $summary[ $key ]['commission'], 2, ',', '.' ) ); ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<table class="tbmm-table alc-sales-tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Registratiedatum', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'ID', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Campagne', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Referentie', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Productgroep', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tbmm' ); ?></th>
					<th class="num"><?php esc_html_e( 'Bestelbedr', 'tbmm' ); ?></th>
					<th class="num"><?php esc_html_e( 'Commissie', 'tbmm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $transactions as $tx ) :
					$t          = (object) $tx;
					$status_key = strtolower( $t->transactionStatus ?? 'pending' );
					$color      = $status_colors[ $status_key ] ?? '#888';
					$label      = $status_labels[ $status_key ] ?? $status_key;

					$campaign = is_object( $t->campaign ?? null ) ? ( $t->campaign->name ?? '—' ) : '—';
					$reg_date = ! empty( $t->registrationDate )
						? gmdate( 'd-m-Y H:i', strtotime( $t->registrationDate ) )
						: '—';
					$order_amt  = isset( $t->orderAmount ) ? '€ ' . number_format( (float) $t->orderAmount, 2, ',', '.' ) : '—';
					$commission = isset( $t->commission )  ? '€ ' . number_format( (float) $t->commission,  2, ',', '.' ) : '—';
					$product    = $t->campaignProduct->name ?? ( $t->description ?? '—' );
				?>
				<tr>
					<td><?php echo esc_html( $reg_date ); ?></td>
					<td style="color:#646970; font-size:12px;"><?php echo esc_html( '#' . ( $t->ID ?? '?' ) ); ?></td>
					<td><?php echo esc_html( $campaign ); ?></td>
					<td><?php echo esc_html( $t->reference ?? '—' ); ?></td>
					<td><?php echo esc_html( $product ); ?></td>
					<td><span class="alc-badge" style="background:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $label ); ?></span></td>
					<td class="num"><?php echo esc_html( $order_amt ); ?></td>
					<td class="num"><?php echo esc_html( $commission ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
