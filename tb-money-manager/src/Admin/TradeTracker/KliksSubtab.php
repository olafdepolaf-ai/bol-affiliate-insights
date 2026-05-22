<?php

namespace TuinenBalkon\TBMoneyManager\Admin\TradeTracker;

use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class KliksSubtab {

	private TradeTrackerService $service;

	public function __construct( TradeTrackerService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		$current_year  = (int) gmdate( 'Y' );
		$selected_year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : $current_year;
		$selected_year = max( 2015, min( $current_year, $selected_year ) );
		$per_page      = 50;
		$current_page  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

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

		$subtab_url = admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=kliks&jaar=' . $selected_year );
		?>
		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="tb-money-manager" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="kliks" />
			<label for="tbmm_kliks_jaar" style="font-weight:600;"><?php esc_html_e( 'Jaar:', 'tbmm' ); ?></label>
			<select id="tbmm_kliks_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<?php
		$clicks = $this->service->get_clicks_year( $site_id, $selected_year );

		if ( is_wp_error( $clicks ) ) {
			echo '<div class="notice notice-error inline"><p><strong>'
				. esc_html__( 'Fout:', 'tbmm' )
				. '</strong> ' . esc_html( $clicks->get_error_message() ) . '</p></div>';
			return;
		}

		if ( empty( $clicks ) ) {
			echo '<p><em>'
				. sprintf(
					/* translators: %s = year number */
					esc_html__( 'Geen kliks gevonden voor %s.', 'tbmm' ),
					esc_html( $selected_year )
				  )
				. '</em></p>';
			return;
		}

		$total_clicks = count( $clicks );
		$total_pages  = (int) ceil( $total_clicks / $per_page );
		$current_page = min( $current_page, $total_pages );
		$offset       = ( $current_page - 1 ) * $per_page;
		$page_clicks  = array_slice( $clicks, $offset, $per_page );

		if ( isset( $_GET['cache_cleared'] ) ) : ?>
		<div class="notice notice-success is-dismissible" style="margin-bottom:10px;"><p><?php esc_html_e( 'Cache gewist — data wordt opnieuw opgehaald bij TradeTracker.', 'tbmm' ); ?></p></div>
		<?php endif;

		$fetched_at  = $this->service->get_clicks_fetched_at( $site_id, $selected_year );
		$fetched_str = $fetched_at
			? sprintf(
				/* translators: %s = date/time of last fetch */
				__( 'Bijgewerkt: %s · vernieuwt elke 24 uur', 'tbmm' ),
				date_i18n( 'd M Y \o\m H:i', $fetched_at )
			  )
			: __( 'Tijdstip onbekend — klik Vernieuwen om de cache te vullen', 'tbmm' );
		$flush_url   = wp_nonce_url(
			admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=kliks&jaar=' . $selected_year . '&tbmm_flush_clicks=1' ),
			'tbmm_flush_clicks_' . $selected_year,
			'tbmm_flush_clicks'
		);
		?>
		<p class="alc-clicks-meta" style="display:flex; align-items:center; gap:14px;">
			<span>
				<?php
				printf(
					/* translators: 1: click count, 2: year, 3: current page, 4: total pages */
					esc_html__( '%1$s kliks in %2$s — pagina %3$s van %4$s', 'tbmm' ),
					esc_html( number_format( $total_clicks, 0, ',', '.' ) ),
					esc_html( $selected_year ),
					esc_html( $current_page ),
					esc_html( $total_pages )
				);
				?>
			</span>
			<span style="color:#aaa; font-size:12px;"><?php echo esc_html( $fetched_str ); ?></span>
			<a href="<?php echo esc_url( $flush_url ); ?>" class="button button-small">↻ <?php esc_html_e( 'Vernieuwen', 'tbmm' ); ?></a>
		</p>

		<table class="tbmm-table alc-clicks-tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Datum', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'ID', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Campagne', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Referentie', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Apparaat', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Land', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Herkomst', 'tbmm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $page_clicks as $click ) :
					$c        = (object) $click;
					$campaign = is_object( $c->campaign ?? null ) ? ( $c->campaign->name ?? '—' ) : '—';
					$reg_date = ! empty( $c->registrationDate )
						? gmdate( 'd-m-Y H:i', strtotime( $c->registrationDate ) )
						: '—';
					$device   = $c->deviceType ?? ( $c->device ?? ( $c->deviceName ?? '—' ) );
					$country  = $c->countryCode ?? ( $c->country ?? '—' );
					$referrer = $c->referrer ?? ( $c->referrerURL ?? ( $c->referrerUrl ?? '' ) );
				?>
				<tr>
					<td><?php echo esc_html( $reg_date ); ?></td>
					<td class="alc-id"><?php echo esc_html( '#' . ( $c->ID ?? '?' ) ); ?></td>
					<td><?php echo esc_html( $campaign ); ?></td>
					<td class="alc-ref"><?php echo esc_html( $c->reference ?? '—' ); ?></td>
					<td><?php echo esc_html( (string) $device ); ?></td>
					<td><?php echo esc_html( (string) $country ); ?></td>
					<td class="alc-src" title="<?php echo esc_attr( $referrer ); ?>">
						<?php echo esc_html( $referrer ?: '—' ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="tbmm-pagination">
			<?php if ( $current_page > 1 ) : ?>
			<a href="<?php echo esc_url( $subtab_url . '&paged=' . ( $current_page - 1 ) ); ?>">‹ <?php esc_html_e( 'Vorige', 'tbmm' ); ?></a>
			<?php endif; ?>

			<?php
			$shown = [];
			for ( $p = 1; $p <= $total_pages; $p++ ) {
				if ( $p === 1 || $p === $total_pages || abs( $p - $current_page ) <= 2 ) {
					$shown[] = $p;
				}
			}
			$prev = null;
			foreach ( $shown as $p ) :
				if ( $prev !== null && $p - $prev > 1 ) :
					?><span class="dots">…</span><?php
				endif;
				if ( $p === $current_page ) :
					?><span class="current"><?php echo esc_html( $p ); ?></span><?php
				else :
					?><a href="<?php echo esc_url( $subtab_url . '&paged=' . $p ); ?>"><?php echo esc_html( $p ); ?></a><?php
				endif;
				$prev = $p;
			endforeach;
			?>

			<?php if ( $current_page < $total_pages ) : ?>
			<a href="<?php echo esc_url( $subtab_url . '&paged=' . ( $current_page + 1 ) ); ?>"><?php esc_html_e( 'Volgende', 'tbmm' ); ?> ›</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
	}
}
