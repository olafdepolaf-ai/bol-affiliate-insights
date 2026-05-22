<?php

namespace TuinenBalkon\TBMoneyManager\Admin\TradeTracker;

use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class RapportSubtab {

	private TradeTrackerService $service;

	public function __construct( TradeTrackerService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		$base_url      = admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=rapport' );
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

		$fetched_at  = $this->service->get_report_fetched_at( $site_id, $selected_year );
		$ttl_label   = ( $selected_year < (int) gmdate( 'Y' ) ) ? __( '24 uur', 'tbmm' ) : __( '1 uur', 'tbmm' );
		$fetched_str = $fetched_at
			? sprintf(
				/* translators: 1: date/time, 2: TTL interval */
				__( 'Bijgewerkt: %1$s · vernieuwt elke %2$s', 'tbmm' ),
				date_i18n( 'd M Y \o\m H:i', $fetched_at ),
				$ttl_label
			  )
			: __( 'Tijdstip onbekend', 'tbmm' );
		$flush_url   = wp_nonce_url(
			admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=rapport&jaar=' . $selected_year . '&tbmm_flush_rapport=1' ),
			'tbmm_flush_rapport_' . $selected_year,
			'tbmm_flush_rapport'
		);

		if ( isset( $_GET['cache_cleared'] ) ) :
		?>
		<div class="notice notice-success is-dismissible" style="margin-bottom:10px;"><p><?php esc_html_e( 'Cache gewist — data wordt opnieuw opgehaald bij TradeTracker.', 'tbmm' ); ?></p></div>
		<?php endif; ?>

		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="tb-money-manager" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="rapport" />
			<label for="tbmm_jaar" style="font-weight:600;"><?php esc_html_e( 'Jaar:', 'tbmm' ); ?></label>
			<select id="tbmm_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<p style="display:flex; align-items:center; gap:14px; font-size:13px; color:#646970; margin-bottom:16px;">
			<span><?php echo esc_html( $fetched_str ); ?></span>
			<a href="<?php echo esc_url( $flush_url ); ?>" class="button button-small">↻ <?php esc_html_e( 'Vernieuwen', 'tbmm' ); ?></a>
		</p>

		<?php
		$months_data = $this->service->get_report_year( $site_id, $selected_year );

		if ( is_wp_error( $months_data ) ) {
			echo '<div class="notice notice-error inline"><p><strong>'
				. esc_html__( 'Fout:', 'tbmm' )
				. '</strong> ' . esc_html( $months_data->get_error_message() ) . '</p></div>';
			return;
		}

		$cols = [
			'overallClickCount' => __( 'Kliks', 'tbmm' ),
			'uniqueClickCount'  => __( 'Kliks uniek', 'tbmm' ),
			'leadCount'         => __( 'Leads #', 'tbmm' ),
			'leadCommission'    => __( 'Leads €', 'tbmm' ),
			'saleCount'         => __( 'Sales #', 'tbmm' ),
			'saleCommission'    => __( 'Sales €', 'tbmm' ),
			'totalCommission'   => __( 'Totaal €', 'tbmm' ),
		];
		$money_cols = [ 'leadCommission', 'saleCommission', 'totalCommission' ];

		$totals = array_fill_keys( array_keys( $cols ), 0.0 );
		?>

		<table class="tbmm-table alc-report widefat" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Maand', 'tbmm' ); ?></th>
					<?php foreach ( $cols as $key => $label ) : ?>
					<th><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php for ( $m = 1; $m <= 12; $m++ ) :
					$r      = isset( $months_data[ $m ] ) ? (object) $months_data[ $m ] : null;
					$future = is_null( $r );
				?>
				<tr class="<?php echo $future ? 'alc-future' : ''; ?>">
					<td><?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ); ?></td>
					<?php foreach ( $cols as $key => $label ) :
						if ( $future ) {
							echo '<td>—</td>';
							continue;
						}
						$val = isset( $r->$key ) ? (float) $r->$key : 0.0;
						$totals[ $key ] += $val;
						$is_money = in_array( $key, $money_cols, true );
						$zero     = $val == 0.0;
						$display  = $is_money
							? ( $zero ? '—' : '€ ' . number_format( $val, 2, ',', '.' ) )
							: ( $zero ? '—' : number_format( (int) $val, 0, ',', '.' ) );
					?>
					<td class="<?php echo $zero ? 'alc-zero' : ''; ?>"><?php echo esc_html( $display ); ?></td>
					<?php endforeach; ?>
				</tr>
				<?php endfor; ?>
			</tbody>
			<tfoot>
				<tr class="alc-total">
					<td><?php esc_html_e( 'Totaal', 'tbmm' ); ?></td>
					<?php foreach ( $cols as $key => $label ) :
						$val      = $totals[ $key ];
						$is_money = in_array( $key, $money_cols, true );
						$display  = $is_money
							? '€ ' . number_format( $val, 2, ',', '.' )
							: number_format( (int) $val, 0, ',', '.' );
					?>
					<td><?php echo esc_html( $display ); ?></td>
					<?php endforeach; ?>
				</tr>
			</tfoot>
		</table>

		<?php
		$material_urls = $this->service->get_text_material_urls( $site_id );
		if ( is_wp_error( $material_urls ) ) {
			$material_urls = [];
		}

		$ref_page = isset( $_GET['refpaged'] ) ? max( 1, (int) $_GET['refpaged'] ) : 1;
		$this->render_top_links_table( $site_id, $selected_year, $ref_page, $material_urls );
	}

	/** @param array<string,string> $material_urls  campagneID => base tracking URL */
	private function render_top_links_table( string $site_id, int $selected_year, int $current_page, array $material_urls = [] ): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=rapport&jaar=' . $selected_year );

		$clicks = $this->service->get_clicks_year( $site_id, $selected_year );
		if ( is_wp_error( $clicks ) || empty( $clicks ) ) {
			return;
		}

		$groups = [];
		foreach ( $clicks as $click ) {
			$c             = (object) $click;
			$campaign_id   = is_object( $c->campaign ?? null ) ? (string) ( $c->campaign->ID   ?? '' ) : '';
			$campaign_name = is_object( $c->campaign ?? null ) ? ( $c->campaign->name ?? '—' )         : '—';
			$reference     = (string) ( $c->reference ?? '' );
			$key           = $campaign_id . '|' . $reference;

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [
					'campaign_id'   => $campaign_id,
					'campaign_name' => $campaign_name,
					'reference'     => $reference,
					'count'         => 0,
				];
			}
			$groups[ $key ]['count']++;
		}

		usort( $groups, fn( $a, $b ) => $b['count'] - $a['count'] );

		$per_page     = 25;
		$total        = count( $groups );
		$total_pages  = (int) ceil( $total / $per_page );
		$current_page = max( 1, min( $current_page, $total_pages ) );
		$offset       = ( $current_page - 1 ) * $per_page;
		$page_groups  = array_slice( $groups, $offset, $per_page );
		?>

		<h3 style="margin-top:32px;"><?php esc_html_e( 'Meest geklikt — per link', 'tbmm' ); ?></h3>
		<p style="color:#646970; font-size:13px; margin-top:-8px; margin-bottom:14px;">
			<?php
			printf(
				/* translators: 1: unique link count, 2: total click count */
				esc_html__( '%1$s unieke links · %2$s kliks totaal', 'tbmm' ),
				esc_html( number_format_i18n( $total ) ),
				esc_html( number_format_i18n( count( $clicks ) ) )
			);
			?>
		</p>

		<?php $max_count = ! empty( $page_groups ) ? $page_groups[0]['count'] : 1; ?>

		<table class="tbmm-table alc-toplinks">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'Campagne', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Referentie', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Kliks', 'tbmm' ); ?></th>
					<th title="<?php esc_attr_e( 'Open link in nieuw tabblad', 'tbmm' ); ?>">↗</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $page_groups as $i => $group ) :
					$rank      = $offset + $i + 1;
					$link_url  = '';
					$campaign_id = $group['campaign_id'];
					if ( $campaign_id !== '' ) {
						$base = $material_urls[ $campaign_id ] ?? '';
						if ( $base !== '' ) {
							$link_url = $base . rawurlencode( $group['reference'] );
						} else {
							$link_url = 'https://tc.tradetracker.net/?c=' . rawurlencode( $campaign_id )
								. '&m=12&a=' . rawurlencode( $site_id );
							if ( $group['reference'] !== '' ) {
								$link_url .= '&r=' . rawurlencode( $group['reference'] );
							}
						}
					}
					$bar_pct = $max_count > 0 ? round( ( $group['count'] / $max_count ) * 100 ) : 0;
				?>
				<tr>
					<td style="color:#646970; font-size:12px;"><?php echo esc_html( $rank ); ?></td>
					<td><?php echo esc_html( $group['campaign_name'] ); ?></td>
					<td class="alc-tl-ref"><?php echo esc_html( $group['reference'] !== '' ? $group['reference'] : '—' ); ?></td>
					<td class="alc-tl-count">
						<?php echo esc_html( $group['count'] ); ?>
						<div class="tbmm-bar"><div class="tbmm-bar-fill" style="width:<?php echo esc_attr( $bar_pct ); ?>%"></div></div>
					</td>
					<td class="alc-tl-link">
						<?php if ( $link_url ) : ?>
						<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $group['campaign_name'] . ( $group['reference'] ? ' · ' . $group['reference'] : '' ) ); ?>">↗</a>
						<?php else : ?>
						—
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="tbmm-pagination">
			<?php if ( $current_page > 1 ) : ?>
			<a href="<?php echo esc_url( $base_url . '&refpaged=' . ( $current_page - 1 ) ); ?>">‹ <?php esc_html_e( 'Vorige', 'tbmm' ); ?></a>
			<?php endif; ?>
			<?php
			$prev = null;
			for ( $p = 1; $p <= $total_pages; $p++ ) :
				if ( $p !== 1 && $p !== $total_pages && abs( $p - $current_page ) > 2 ) {
					if ( $prev !== null && abs( $prev - $current_page ) <= 2 ) {
						echo '<span class="dots">…</span>';
					}
					$prev = $p;
					continue;
				}
				if ( $p === $current_page ) :
					?><span class="current"><?php echo esc_html( $p ); ?></span><?php
				else :
					?><a href="<?php echo esc_url( $base_url . '&refpaged=' . $p ); ?>"><?php echo esc_html( $p ); ?></a><?php
				endif;
				$prev = $p;
			endfor;
			?>
			<?php if ( $current_page < $total_pages ) : ?>
			<a href="<?php echo esc_url( $base_url . '&refpaged=' . ( $current_page + 1 ) ); ?>"><?php esc_html_e( 'Volgende', 'tbmm' ); ?> ›</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
	}
}
