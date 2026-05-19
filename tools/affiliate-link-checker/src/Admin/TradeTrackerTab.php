<?php

namespace TuinenBalkon\AffiliateLinkChecker\Admin;

use TuinenBalkon\AffiliateLinkChecker\Service\TradeTrackerService;

class TradeTrackerTab {

	private TradeTrackerService $service;

	private static array $month_names = [
		1 => 'Januari', 2 => 'Februari', 3 => 'Maart',     4 => 'April',
		5 => 'Mei',     6 => 'Juni',     7 => 'Juli',       8 => 'Augustus',
		9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December',
	];

	public function __construct( TradeTrackerService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=affiliate-link-checker&tab=tradetracker' );
		$subtab   = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'sales';

		// Left tabs (ordered), settings floated right
		$left_subtabs = [ 'sales' => 'Sales', 'kliks' => 'Kliks', 'rapport' => 'Rapport' ];
		?>
		<style>
			.alc-subtab-nav { display:flex; align-items:flex-end; gap:4px; margin-bottom:20px; border-bottom:1px solid #c3c4c7; padding-bottom:0; }
			.alc-subtab-nav a { display:inline-block; padding:6px 14px; text-decoration:none; font-size:13px; color:#2271b1; border:1px solid transparent; border-bottom:none; border-radius:3px 3px 0 0; margin-bottom:-1px; }
			.alc-subtab-nav a:hover { background:#f0f0f1; color:#135e96; }
			.alc-subtab-nav a.active { background:#fff; border-color:#c3c4c7; color:#1d2327; font-weight:600; }
			.alc-subtab-nav .alc-subtab-settings { margin-left:auto; font-size:12px; color:#646970; border-color:transparent !important; }
			.alc-subtab-nav .alc-subtab-settings:hover { color:#135e96; background:#f0f0f1; }
			.alc-subtab-nav .alc-subtab-settings.active { color:#1d2327; background:#fff; border-color:#c3c4c7 !important; }
		</style>
		<nav class="alc-subtab-nav">
			<?php foreach ( $left_subtabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $base_url . '&subtab=' . $slug ); ?>"
			   class="<?php echo $subtab === $slug ? 'active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
			<a href="<?php echo esc_url( $base_url . '&subtab=settings' ); ?>"
			   class="alc-subtab-settings <?php echo $subtab === 'settings' ? 'active' : ''; ?>">
				⚙ Instellingen
			</a>
		</nav>

		<?php
		match ( $subtab ) {
			'kliks'   => $this->render_clicks_subtab(),
			'rapport' => $this->render_rapport_subtab( $base_url ),
			'settings'=> $this->render_settings_subtab(),
			default   => $this->render_sales_subtab(),
		};
	}

	// -------------------------------------------------------------------------
	// Instellingen subtab
	// -------------------------------------------------------------------------

	private function render_settings_subtab(): void {
		$notice = '';

		if ( isset( $_POST['alc_tt_save_settings'] ) && check_admin_referer( 'alc_tt_settings', 'alc_tt_nonce' ) ) {
			update_option( 'alc_tt_customer_id', sanitize_text_field( wp_unslash( $_POST['alc_tt_customer_id'] ?? '' ) ) );
			update_option( 'alc_tt_access_key',  sanitize_text_field( wp_unslash( $_POST['alc_tt_access_key'] ?? '' ) ) );
			$this->service->clear_cache();
			$notice = '<div class="notice notice-success inline"><p>Instellingen opgeslagen en cache gewist.</p></div>';
		}

		if ( isset( $_POST['alc_tt_clear_cache'] ) && check_admin_referer( 'alc_tt_settings', 'alc_tt_nonce' ) ) {
			$this->service->clear_cache();
			$notice = '<div class="notice notice-success inline"><p>Cache gewist.</p></div>';
		}

		$customer_id = get_option( 'alc_tt_customer_id', '' );
		$access_key  = get_option( 'alc_tt_access_key', '' );
		$has_creds   = ! empty( $customer_id ) && ! empty( $access_key );

		echo wp_kses_post( $notice );
		?>

		<form method="post">
			<?php wp_nonce_field( 'alc_tt_settings', 'alc_tt_nonce' ); ?>
			<h3 style="margin-top:0;">API inloggegevens</h3>
			<table class="form-table" style="max-width:560px;">
				<tr>
					<th scope="row"><label for="alc_tt_customer_id">Klant-ID</label></th>
					<td><input type="text" id="alc_tt_customer_id" name="alc_tt_customer_id"
						value="<?php echo esc_attr( $customer_id ); ?>"
						class="regular-text" placeholder="bijv. 26710" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="alc_tt_access_key">Toegangssleutel</label></th>
					<td><input type="password" id="alc_tt_access_key" name="alc_tt_access_key"
						value="<?php echo esc_attr( $access_key ); ?>"
						class="regular-text" /></td>
				</tr>
			</table>
			<button type="submit" name="alc_tt_save_settings" class="button button-primary">Opslaan</button>
			<?php if ( $has_creds ) : ?>
			<button type="submit" name="alc_tt_clear_cache" class="button" style="margin-left:8px;">Cache vernieuwen</button>
			<?php endif; ?>
		</form>

		<?php if ( ! $has_creds ) : ?>
		<p style="margin-top:16px;"><em>Vul de inloggegevens in om data op te halen.</em></p>
		<?php return; endif;

		$sites = $this->service->get_affiliate_sites();
		if ( is_wp_error( $sites ) ) {
			echo '<div class="notice notice-error inline" style="margin-top:16px;"><p><strong>Verbindingsfout:</strong> ' . esc_html( $sites->get_error_message() ) . '</p></div>';
			return;
		}
		if ( empty( $sites ) ) {
			echo '<p><em>Geen affiliate sites gevonden.</em></p>';
			return;
		}

		$primary_site = reset( $sites );
		$site_id      = is_object( $primary_site ) ? $primary_site->ID : ( $primary_site['ID'] ?? '' );

		$this->render_account_info( $sites );
		$this->render_campaigns( (string) $site_id );
	}

	private function render_account_info( array $sites ): void {
		?>
		<h3 style="margin-top:24px;">Affiliate sites</h3>
		<table class="widefat striped" style="max-width:800px; margin-bottom:24px;">
			<thead>
				<tr><th>ID</th><th>Naam</th><th>URL</th><th>Type</th><th>Status</th></tr>
			</thead>
			<tbody>
				<?php foreach ( $sites as $site ) :
					$s = (object) $site;
				?>
				<tr>
					<td><?php echo esc_html( $s->ID ?? '—' ); ?></td>
					<td><?php echo esc_html( $s->name ?? '—' ); ?></td>
					<td><?php if ( ! empty( $s->URL ) ) : ?><a href="<?php echo esc_url( $s->URL ); ?>" target="_blank"><?php echo esc_html( $s->URL ); ?></a><?php else : ?>—<?php endif; ?></td>
					<td><?php echo esc_html( $s->info->type->name ?? '—' ); ?></td>
					<td><?php echo esc_html( $s->info->status ?? '—' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_campaigns( string $site_id ): void {
		$campaigns = $this->service->get_campaigns( $site_id );
		if ( is_wp_error( $campaigns ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $campaigns->get_error_message() ) . '</p></div>';
			return;
		}
		?>
		<h3>Geabonneerde campagnes (<?php echo count( $campaigns ); ?>)</h3>
		<?php if ( empty( $campaigns ) ) : ?>
		<p><em>Geen geabonneerde campagnes.</em></p>
		<?php return; endif; ?>
		<table class="widefat striped" style="max-width:900px; margin-bottom:24px;">
			<thead>
				<tr><th>ID</th><th>Naam</th><th>Categorie</th><th>Commissie type</th><th>Status</th><th></th></tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $campaign ) :
					$c = (object) $campaign;
				?>
				<tr>
					<td><?php echo esc_html( $c->ID ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->name ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->info->category->name ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->info->commission->type ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->info->assignmentStatus ?? '—' ); ?></td>
					<td><?php if ( ! empty( $c->URL ) ) : ?><a href="<?php echo esc_url( $c->URL ); ?>" target="_blank">↗</a><?php endif; ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	// -------------------------------------------------------------------------
	// Rapport subtab
	// -------------------------------------------------------------------------

	private function render_rapport_subtab( string $base_url ): void {
		$current_year = (int) gmdate( 'Y' );
		$selected_year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : $current_year;
		$selected_year = max( 2015, min( $current_year, $selected_year ) );

		$customer_id = get_option( 'alc_tt_customer_id', '' );
		$access_key  = get_option( 'alc_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
			return;
		}

		$sites = $this->service->get_affiliate_sites();
		if ( is_wp_error( $sites ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $sites->get_error_message() ) . '</p></div>';
			return;
		}
		$primary_site = reset( $sites );
		$site_id      = (string) ( is_object( $primary_site ) ? $primary_site->ID : ( $primary_site['ID'] ?? '' ) );

		?>
		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="affiliate-link-checker" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="rapport" />
			<label for="alc_jaar" style="font-weight:600;">Jaar:</label>
			<select id="alc_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<?php
		$months_data = $this->service->get_report_year( $site_id, $selected_year );

		if ( is_wp_error( $months_data ) ) {
			echo '<div class="notice notice-error inline"><p><strong>Fout:</strong> ' . esc_html( $months_data->get_error_message() ) . '</p></div>';
			return;
		}

		$cols = [
			'overallClickCount' => 'Kliks',
			'uniqueClickCount'  => 'Kliks uniek',
			'leadCount'         => 'Leads #',
			'leadCommission'    => 'Leads €',
			'saleCount'         => 'Sales #',
			'saleCommission'    => 'Sales €',
			'totalCommission'   => 'Totaal €',
		];
		$money_cols = [ 'leadCommission', 'saleCommission', 'totalCommission' ];

		// Totalen berekenen
		$totals = array_fill_keys( array_keys( $cols ), 0.0 );
		?>

		<style>
			.alc-report th, .alc-report td { padding:7px 12px; border:1px solid #e0e0e0; text-align:right; white-space:nowrap; }
			.alc-report th { background:#f6f7f7; font-weight:600; }
			.alc-report td:first-child, .alc-report th:first-child { text-align:left; }
			.alc-report tr.alc-future td { color:#bbb; }
			.alc-report tr.alc-total td { background:#f0f6fc; font-weight:700; border-top:2px solid #2271b1; }
			.alc-report td.alc-zero { color:#bbb; }
		</style>

		<table class="widefat alc-report" style="max-width:900px; border-collapse:collapse;">
			<thead>
				<tr>
					<th>Maand</th>
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
					<td><?php echo esc_html( self::$month_names[ $m ] ); ?></td>
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
					<td>Totaal</td>
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
	}

	// -------------------------------------------------------------------------
	// Kliks subtab
	// -------------------------------------------------------------------------

	private function render_clicks_subtab(): void {
		$current_year  = (int) gmdate( 'Y' );
		$selected_year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : $current_year;
		$selected_year = max( 2015, min( $current_year, $selected_year ) );
		$per_page      = 50;
		$current_page  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$customer_id = get_option( 'alc_tt_customer_id', '' );
		$access_key  = get_option( 'alc_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
			return;
		}

		$sites = $this->service->get_affiliate_sites();
		if ( is_wp_error( $sites ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $sites->get_error_message() ) . '</p></div>';
			return;
		}
		$primary_site = reset( $sites );
		$site_id      = (string) ( is_object( $primary_site ) ? $primary_site->ID : ( $primary_site['ID'] ?? '' ) );

		// Base URL for this subtab (year/page navigation)
		$subtab_url = admin_url( 'admin.php?page=affiliate-link-checker&tab=tradetracker&subtab=kliks&jaar=' . $selected_year );
		?>
		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="affiliate-link-checker" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="kliks" />
			<label for="alc_kliks_jaar" style="font-weight:600;">Jaar:</label>
			<select id="alc_kliks_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<?php
		$clicks = $this->service->get_clicks_year( $site_id, $selected_year );

		if ( is_wp_error( $clicks ) ) {
			echo '<div class="notice notice-error inline"><p><strong>Fout:</strong> ' . esc_html( $clicks->get_error_message() ) . '</p></div>';
			return;
		}

		if ( empty( $clicks ) ) {
			echo '<p><em>Geen kliks gevonden voor ' . esc_html( $selected_year ) . '.</em></p>';
			return;
		}

		$total_clicks = count( $clicks );
		$total_pages  = (int) ceil( $total_clicks / $per_page );
		$current_page = min( $current_page, $total_pages );
		$offset       = ( $current_page - 1 ) * $per_page;
		$page_clicks  = array_slice( $clicks, $offset, $per_page );
		?>

		<style>
			.alc-clicks-tbl { border-collapse:collapse; width:100%; max-width:1100px; }
			.alc-clicks-tbl th, .alc-clicks-tbl td { padding:7px 12px; border:1px solid #e0e0e0; font-size:13px; }
			.alc-clicks-tbl th { background:#f6f7f7; font-weight:600; text-align:left; white-space:nowrap; }
			.alc-clicks-tbl td.alc-ref { font-family:monospace; font-size:12px; }
			.alc-clicks-tbl td.alc-id  { color:#646970; font-size:12px; }
			.alc-clicks-tbl td.alc-src { max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px; color:#646970; }

			.alc-pagination { display:flex; align-items:center; gap:6px; margin-top:16px; flex-wrap:wrap; }
			.alc-pagination a, .alc-pagination span { display:inline-block; padding:4px 10px; border:1px solid #c3c4c7; border-radius:3px; font-size:13px; text-decoration:none; color:#2271b1; background:#fff; }
			.alc-pagination span.current { background:#2271b1; color:#fff; border-color:#2271b1; font-weight:600; }
			.alc-pagination span.dots { border:none; color:#646970; }
			.alc-pagination a:hover { background:#f0f0f1; }
			.alc-clicks-meta { color:#646970; font-size:13px; margin-bottom:10px; }
		</style>

		<p class="alc-clicks-meta">
			<?php echo esc_html( number_format( $total_clicks, 0, ',', '.' ) ); ?> kliks in <?php echo esc_html( $selected_year ); ?>
			— pagina <?php echo esc_html( $current_page ); ?> van <?php echo esc_html( $total_pages ); ?>
		</p>

		<table class="alc-clicks-tbl">
			<thead>
				<tr>
					<th>Datum</th>
					<th>ID</th>
					<th>Campagne</th>
					<th>Referentie</th>
					<th>Apparaat</th>
					<th>Land</th>
					<th>Herkomst</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $page_clicks as $click ) :
					$c        = (object) $click;
					$campaign = is_object( $c->campaign ?? null ) ? ( $c->campaign->name ?? '—' ) : '—';
					$reg_date = ! empty( $c->registrationDate )
						? gmdate( 'd-m-Y H:i', strtotime( $c->registrationDate ) )
						: '—';
					// device: check various possible property names
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
		<div class="alc-pagination">
			<?php if ( $current_page > 1 ) : ?>
			<a href="<?php echo esc_url( $subtab_url . '&paged=' . ( $current_page - 1 ) ); ?>">‹ Vorige</a>
			<?php endif; ?>

			<?php
			// Show first, last, and a window around current page
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
			<a href="<?php echo esc_url( $subtab_url . '&paged=' . ( $current_page + 1 ) ); ?>">Volgende ›</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Sales subtab
	// -------------------------------------------------------------------------

	private function render_sales_subtab(): void {
		$current_year  = (int) gmdate( 'Y' );
		$selected_year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : $current_year;
		$selected_year = max( 2015, min( $current_year, $selected_year ) );

		$customer_id = get_option( 'alc_tt_customer_id', '' );
		$access_key  = get_option( 'alc_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
			return;
		}

		$sites = $this->service->get_affiliate_sites();
		if ( is_wp_error( $sites ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $sites->get_error_message() ) . '</p></div>';
			return;
		}
		$primary_site = reset( $sites );
		$site_id      = (string) ( is_object( $primary_site ) ? $primary_site->ID : ( $primary_site['ID'] ?? '' ) );
		?>

		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="affiliate-link-checker" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="sales" />
			<label for="alc_sales_jaar" style="font-weight:600;">Jaar:</label>
			<select id="alc_sales_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<?php
		$transactions = $this->service->get_sales_year( $site_id, $selected_year );

		if ( is_wp_error( $transactions ) ) {
			echo '<div class="notice notice-error inline"><p><strong>Fout:</strong> ' . esc_html( $transactions->get_error_message() ) . '</p></div>';
			return;
		}

		if ( empty( $transactions ) ) {
			echo '<p><em>Geen sales gevonden voor ' . esc_html( $selected_year ) . '.</em></p>';
			return;
		}

		// Samenvattingstotalen berekenen
		$summary = [ 'pending' => [ 'count' => 0, 'commission' => 0.0 ], 'accepted' => [ 'count' => 0, 'commission' => 0.0 ], 'rejected' => [ 'count' => 0, 'commission' => 0.0 ] ];
		foreach ( $transactions as $tx ) {
			$t      = (object) $tx;
			$status = strtolower( $t->transactionStatus ?? 'pending' );
			if ( ! isset( $summary[ $status ] ) ) {
				$summary[ $status ] = [ 'count' => 0, 'commission' => 0.0 ];
			}
			$summary[ $status ]['count']++;
			$summary[ $status ]['commission'] += (float) ( $t->commission ?? 0 );
		}

		$status_labels = [ 'accepted' => 'Geaccepteerd', 'pending' => 'In behandeling', 'rejected' => 'Afgekeurd' ];
		$status_colors = [ 'accepted' => '#00a32a', 'pending' => '#dba617', 'rejected' => '#d63638' ];
		$total_commission = array_sum( array_column( $summary, 'commission' ) );
		$total_count      = array_sum( array_column( $summary, 'count' ) );
		?>

		<style>
			.alc-sales-summary { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
			.alc-sales-card { background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:12px 18px; min-width:160px; }
			.alc-sales-card .alc-card-num { font-size:22px; font-weight:700; }
			.alc-sales-card .alc-card-sub { font-size:12px; color:#646970; margin-top:2px; }
			.alc-sales-card.alc-card-total .alc-card-num { color:#2271b1; }

			.alc-sales-tbl { border-collapse:collapse; width:100%; max-width:1000px; }
			.alc-sales-tbl th, .alc-sales-tbl td { padding:7px 12px; border:1px solid #e0e0e0; font-size:13px; }
			.alc-sales-tbl th { background:#f6f7f7; font-weight:600; text-align:left; white-space:nowrap; }
			.alc-sales-tbl td.num { text-align:right; white-space:nowrap; }
			.alc-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; color:#fff; white-space:nowrap; }
		</style>

		<div class="alc-sales-summary">
			<div class="alc-sales-card alc-card-total">
				<div class="alc-card-num"><?php echo esc_html( $total_count ); ?> sales</div>
				<div class="alc-card-sub">€ <?php echo esc_html( number_format( $total_commission, 2, ',', '.' ) ); ?> totaal</div>
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

		<table class="alc-sales-tbl">
			<thead>
				<tr>
					<th>Registratiedatum</th>
					<th>ID</th>
					<th>Campagne</th>
					<th>Referentie</th>
					<th>Productgroep</th>
					<th>Status</th>
					<th class="num">Bestelbedr</th>
					<th class="num">Commissie</th>
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
