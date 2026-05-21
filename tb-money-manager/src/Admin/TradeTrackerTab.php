<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Service\OrphanedLinkScanner;
use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;
use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class TradeTrackerTab {

	private TradeTrackerService      $service;
	private ThirstyAffiliatesService $ta_service;
	private OrphanedLinkScanner      $orphaned_scanner;

	private static array $month_names = [
		1 => 'Januari', 2 => 'Februari', 3 => 'Maart',     4 => 'April',
		5 => 'Mei',     6 => 'Juni',     7 => 'Juli',       8 => 'Augustus',
		9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December',
	];

	public function __construct(
		TradeTrackerService $service,
		ThirstyAffiliatesService $ta_service,
		OrphanedLinkScanner $orphaned_scanner
	) {
		$this->service          = $service;
		$this->ta_service       = $ta_service;
		$this->orphaned_scanner = $orphaned_scanner;
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker' );
		$subtab   = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'sales';

		// Left tabs (ordered), settings floated right
		$left_subtabs = [ 'sales' => 'Sales', 'kliks' => 'Kliks', 'rapport' => 'Rapport', 'linkgenerator' => 'Linkgenerator', 'fonq' => 'FONQ.nl', 'productfeed' => 'Productbrowser' ];
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
			'kliks'         => $this->render_clicks_subtab(),
			'rapport'       => $this->render_rapport_subtab( $base_url ),
			'linkgenerator' => $this->render_linkgenerator_subtab(),
			'settings'      => $this->render_settings_subtab(),
			'fonq'          => $this->render_fonq_subtab(),
			'productfeed'   => $this->render_productfeed_subtab(),
			default         => $this->render_sales_subtab(),
		};
	}

	// -------------------------------------------------------------------------
	// Instellingen subtab
	// -------------------------------------------------------------------------

	private function render_settings_subtab(): void {
		$notice = '';

		if ( isset( $_POST['tbmm_tt_save_settings'] ) && check_admin_referer( 'tbmm_tt_settings', 'tbmm_tt_nonce' ) ) {
			update_option( 'tbmm_tt_customer_id', sanitize_text_field( wp_unslash( $_POST['tbmm_tt_customer_id'] ?? '' ) ) );
			update_option( 'tbmm_tt_access_key',  sanitize_text_field( wp_unslash( $_POST['tbmm_tt_access_key'] ?? '' ) ) );
			$this->service->clear_cache();
			$notice = '<div class="notice notice-success inline"><p>Instellingen opgeslagen en cache gewist.</p></div>';
		}

		if ( isset( $_POST['tbmm_tt_clear_cache'] ) && check_admin_referer( 'tbmm_tt_settings', 'tbmm_tt_nonce' ) ) {
			$this->service->clear_cache();
			$notice = '<div class="notice notice-success inline"><p>Cache gewist.</p></div>';
		}

		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		$has_creds   = ! empty( $customer_id ) && ! empty( $access_key );

		echo wp_kses_post( $notice );
		?>

		<form method="post">
			<?php wp_nonce_field( 'tbmm_tt_settings', 'tbmm_tt_nonce' ); ?>
			<h3 style="margin-top:0;">API inloggegevens</h3>
			<table class="form-table" style="max-width:560px;">
				<tr>
					<th scope="row"><label for="tbmm_tt_customer_id">Klant-ID</label></th>
					<td><input type="text" id="tbmm_tt_customer_id" name="tbmm_tt_customer_id"
						value="<?php echo esc_attr( $customer_id ); ?>"
						class="regular-text" placeholder="bijv. 26710" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="tbmm_tt_access_key">Toegangssleutel</label></th>
					<td><input type="password" id="tbmm_tt_access_key" name="tbmm_tt_access_key"
						value="<?php echo esc_attr( $access_key ); ?>"
						class="regular-text" /></td>
				</tr>
			</table>
			<button type="submit" name="tbmm_tt_save_settings" class="button button-primary">Opslaan</button>
			<?php if ( $has_creds ) : ?>
			<button type="submit" name="tbmm_tt_clear_cache" class="button" style="margin-left:8px;">Cache vernieuwen</button>
			<?php endif; ?>
		</form>

		<?php if ( ! $has_creds ) : ?>
		<p style="margin-top:16px;"><em>Vul de inloggegevens in om data op te halen.</em></p>
		<?php return; endif;

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline" style="margin-top:16px;"><p><strong>Verbindingsfout:</strong> ' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}

		$sites = $this->service->get_affiliate_sites();
		if ( is_wp_error( $sites ) ) {
			echo '<div class="notice notice-error inline" style="margin-top:16px;"><p><strong>Verbindingsfout:</strong> ' . esc_html( $sites->get_error_message() ) . '</p></div>';
			return;
		}

		$this->render_account_info( $sites );
		$this->render_campaigns( $site_id );
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

		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
			return;
		}

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}

		// Handle cache flush request
		if ( isset( $_GET['tbmm_flush_rapport'] ) && wp_verify_nonce( sanitize_key( $_GET['tbmm_flush_rapport'] ), 'tbmm_flush_rapport_' . $selected_year ) ) {
			$this->service->clear_report_cache( $site_id, $selected_year );
			wp_safe_redirect( admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=rapport&jaar=' . $selected_year . '&cache_cleared=1' ) );
			exit;
		}

		$fetched_at  = $this->service->get_report_fetched_at( $site_id, $selected_year );
		$ttl_label   = ( $selected_year < (int) gmdate( 'Y' ) ) ? '24 uur' : '1 uur';
		$fetched_str = $fetched_at
			? 'Bijgewerkt: ' . date_i18n( 'd M Y \o\m H:i', $fetched_at ) . ' · vernieuwt elke ' . $ttl_label
			: 'Tijdstip onbekend';
		$flush_url   = wp_nonce_url(
			admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=rapport&jaar=' . $selected_year . '&tbmm_flush_rapport=1' ),
			'tbmm_flush_rapport_' . $selected_year,
			'tbmm_flush_rapport'
		);

		if ( isset( $_GET['cache_cleared'] ) ) :
		?>
		<div class="notice notice-success is-dismissible" style="margin-bottom:10px;"><p>Cache gewist — data wordt opnieuw opgehaald bij TradeTracker.</p></div>
		<?php endif; ?>

		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="tb-money-manager" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="rapport" />
			<label for="tbmm_jaar" style="font-weight:600;">Jaar:</label>
			<select id="tbmm_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<p style="display:flex; align-items:center; gap:14px; font-size:13px; color:#646970; margin-bottom:16px;">
			<span><?php echo esc_html( $fetched_str ); ?></span>
			<a href="<?php echo esc_url( $flush_url ); ?>" class="button button-small">↻ Vernieuwen</a>
		</p>

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
		$material_urls = $this->service->get_text_material_urls( $site_id );
		if ( is_wp_error( $material_urls ) ) {
			$material_urls = [];
		}

		$ref_page = isset( $_GET['refpaged'] ) ? max( 1, (int) $_GET['refpaged'] ) : 1;
		$this->render_top_links_table( $site_id, $selected_year, $ref_page, $material_urls );
	}

	/**
	 * Tabel met meest geklikt links (campagne + referentie), gesorteerd op klikcount.
	 * Gratis uit cache — hergebruikt get_clicks_year() data van de Kliks-tab.
	 */
	/** @param array<string,string> $material_urls  campagneID => base tracking URL */
	private function render_top_links_table( string $site_id, int $selected_year, int $current_page, array $material_urls = [] ): void {
		$clicks = $this->service->get_clicks_year( $site_id, $selected_year );
		if ( is_wp_error( $clicks ) || empty( $clicks ) ) {
			return;
		}

		// Groepeer op campagne-ID + referentie, tel kliks
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

		// Sorteer aflopend op klikcount
		usort( $groups, fn( $a, $b ) => $b['count'] - $a['count'] );

		$per_page     = 25;
		$total        = count( $groups );
		$total_pages  = (int) ceil( $total / $per_page );
		$current_page = max( 1, min( $current_page, $total_pages ) );
		$offset       = ( $current_page - 1 ) * $per_page;
		$page_groups  = array_slice( $groups, $offset, $per_page );

		$paged_url = admin_url(
			'admin.php?page=tb-money-manager&tab=tradetracker&subtab=rapport&jaar=' . $selected_year
		);
		?>

		<h3 style="margin-top:32px;">Meest geklikt — per link</h3>
		<p style="color:#646970; font-size:13px; margin-top:-8px; margin-bottom:14px;">
			<?php echo esc_html( $total ); ?> unieke links · <?php echo esc_html( count( $clicks ) ); ?> kliks totaal
		</p>

		<style>
			.alc-toplinks { border-collapse:collapse; width:100%; max-width:720px; }
			.alc-toplinks th, .alc-toplinks td { padding:7px 12px; border:1px solid #e0e0e0; font-size:13px; }
			.alc-toplinks th { background:#f6f7f7; font-weight:600; text-align:left; white-space:nowrap; }
			.alc-toplinks td.alc-tl-count { text-align:right; font-weight:700; font-size:15px; color:#2271b1; white-space:nowrap; }
			.alc-toplinks td.alc-tl-ref { font-family:monospace; font-size:12px; }
			.alc-toplinks td.alc-tl-link { text-align:center; width:32px; }
			.alc-toplinks td.alc-tl-link a { color:#2271b1; text-decoration:none; font-size:15px; }
			.alc-toplinks td.alc-tl-link a:hover { color:#135e96; }
			.alc-tl-bar-wrap { background:#e8f0fb; border-radius:3px; height:6px; min-width:40px; max-width:160px; margin-top:4px; }
			.alc-tl-bar { background:#2271b1; height:6px; border-radius:3px; }
		</style>

		<?php $max_count = ! empty( $page_groups ) ? $page_groups[0]['count'] : 1; ?>

		<table class="alc-toplinks">
			<thead>
				<tr>
					<th>#</th>
					<th>Campagne</th>
					<th>Referentie</th>
					<th>Kliks</th>
					<th title="Open link in nieuw tabblad">↗</th>
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
							// Transparante link: base eindigt met lege referentie-slot (_)
							$link_url = $base . rawurlencode( $group['reference'] );
						} else {
							// Fallback: tc.tradetracker.net
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
						<div class="alc-tl-bar-wrap"><div class="alc-tl-bar" style="width:<?php echo esc_attr( $bar_pct ); ?>%"></div></div>
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
		<div class="alc-pagination" style="margin-top:12px;">
			<?php if ( $current_page > 1 ) : ?>
			<a href="<?php echo esc_url( $paged_url . '&refpaged=' . ( $current_page - 1 ) ); ?>">‹ Vorige</a>
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
					?><a href="<?php echo esc_url( $paged_url . '&refpaged=' . $p ); ?>"><?php echo esc_html( $p ); ?></a><?php
				endif;
				$prev = $p;
			endfor;
			?>
			<?php if ( $current_page < $total_pages ) : ?>
			<a href="<?php echo esc_url( $paged_url . '&refpaged=' . ( $current_page + 1 ) ); ?>">Volgende ›</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
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

		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
			return;
		}

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}

		// Handle cache flush request
		if ( isset( $_GET['tbmm_flush_clicks'] ) && wp_verify_nonce( sanitize_key( $_GET['tbmm_flush_clicks'] ), 'tbmm_flush_clicks_' . $selected_year ) ) {
			$this->service->clear_clicks_cache( $site_id, $selected_year );
			wp_safe_redirect( admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=kliks&jaar=' . $selected_year . '&cache_cleared=1' ) );
			exit;
		}

		// Base URL for this subtab (year/page navigation)
		$subtab_url = admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=kliks&jaar=' . $selected_year );
		?>
		<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
			<input type="hidden" name="page" value="tb-money-manager" />
			<input type="hidden" name="tab" value="tradetracker" />
			<input type="hidden" name="subtab" value="kliks" />
			<label for="tbmm_kliks_jaar" style="font-weight:600;">Jaar:</label>
			<select id="tbmm_kliks_jaar" name="jaar" onchange="this.form.submit()">
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

		<?php
		if ( isset( $_GET['cache_cleared'] ) ) :
		?>
		<div class="notice notice-success is-dismissible" style="margin-bottom:10px;"><p>Cache gewist — data wordt opnieuw opgehaald bij TradeTracker.</p></div>
		<?php endif;

		$fetched_at  = $this->service->get_clicks_fetched_at( $site_id, $selected_year );
		$fetched_str = $fetched_at
			? 'Bijgewerkt: ' . date_i18n( 'd M Y \o\m H:i', $fetched_at ) . ' · vernieuwt elke 24 uur'
			: 'Tijdstip onbekend — klik Vernieuwen om de cache te vullen';
		$flush_url   = wp_nonce_url(
			admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=kliks&jaar=' . $selected_year . '&tbmm_flush_clicks=1' ),
			'tbmm_flush_clicks_' . $selected_year,
			'tbmm_flush_clicks'
		);
		?>
		<p class="alc-clicks-meta" style="display:flex; align-items:center; gap:14px;">
			<span>
				<?php echo esc_html( number_format( $total_clicks, 0, ',', '.' ) ); ?> kliks in <?php echo esc_html( $selected_year ); ?>
				— pagina <?php echo esc_html( $current_page ); ?> van <?php echo esc_html( $total_pages ); ?>
			</span>
			<span style="color:#aaa; font-size:12px;"><?php echo esc_html( $fetched_str ); ?></span>
			<a href="<?php echo esc_url( $flush_url ); ?>" class="button button-small">↻ Vernieuwen</a>
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
	// Linkgenerator subtab
	// -------------------------------------------------------------------------

	private function render_linkgenerator_subtab(): void {
		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
			return;
		}

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}

		$campaigns = $this->service->get_campaigns( $site_id );
		if ( is_wp_error( $campaigns ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $campaigns->get_error_message() ) . '</p></div>';
			return;
		}

		// Tekstlink-materialen ophalen: campagneID => base tracking URL
		// Bij fout: stille fallback naar tc.tradetracker.net (JS handelt dit af)
		$material_urls = $this->service->get_text_material_urls( $site_id );
		if ( is_wp_error( $material_urls ) ) {
			$material_urls = [];
		}

		// Campagnelijst: id, name, url — inclusief domein voor auto-detect; gesorteerd op naam
		$campaign_list = [];
		foreach ( $campaigns as $c ) {
			$c = (object) $c;
			if ( ! empty( $c->ID ) && ! empty( $c->name ) ) {
				$campaign_list[] = [
					'id'   => (string) $c->ID,
					'name' => $c->name,
					'url'  => (string) ( $c->URL ?? '' ),
				];
			}
		}
		usort( $campaign_list, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
		?>

		<style>
			.alc-gen-wrap { max-width:640px; }
			.alc-gen-wrap label { display:block; font-weight:600; font-size:13px; margin-bottom:4px; }
			.alc-gen-wrap .alc-gen-field { margin-bottom:18px; }
			.alc-gen-wrap select, .alc-gen-wrap input[type=text], .alc-gen-wrap input[type=url] { width:100%; max-width:560px; }
			.alc-gen-url-hint { font-size:12px; margin-top:5px; padding:4px 8px; border-radius:3px; display:none; }
			.alc-gen-url-hint.success { background:#edfaef; color:#00a32a; border:1px solid #b8e6be; }
			.alc-gen-url-hint.warning { background:#fcf9e8; color:#996800; border:1px solid #f0d97e; }
			.alc-gen-result { background:#f0f6fc; border:1px solid #c3d9f0; border-radius:4px; padding:12px 16px; margin-top:20px; display:none; }
			.alc-gen-result label { font-weight:600; font-size:12px; color:#1d2327; margin-bottom:6px; }
			.alc-gen-result-url { font-family:monospace; font-size:13px; word-break:break-all; color:#1d2327; }
			.alc-gen-source { font-size:11px; color:#646970; margin-top:6px; }
			.alc-gen-copy { margin-top:10px; }
			.alc-gen-copied { color:#00a32a; font-size:12px; margin-left:8px; display:none; }
		</style>

		<div class="alc-gen-wrap">
			<div class="alc-gen-field">
				<label for="alc-gen-campaign">Campagne</label>
				<select id="alc-gen-campaign">
					<option value="">— Selecteer campagne —</option>
					<?php foreach ( $campaign_list as $c ) : ?>
					<option value="<?php echo esc_attr( $c['id'] ); ?>"><?php echo esc_html( $c['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description" style="margin-top:4px;">Alleen geaccepteerde campagnes.</p>
			</div>

			<div class="alc-gen-field">
				<label for="alc-gen-url">Doel-URL <span style="font-weight:400; color:#646970;">(leeg = homepage van campagne)</span></label>
				<input type="url" id="alc-gen-url" placeholder="https://www.voorbeeld.nl/pagina/" class="regular-text" />
				<div class="alc-gen-url-hint" id="alc-gen-url-hint"></div>
			</div>

			<div class="alc-gen-field">
				<label for="alc-gen-ref">Referentie <span style="font-weight:400; color:#646970;">(optioneel)</span></label>
				<input type="text" id="alc-gen-ref" placeholder="bijv. loopkamille" class="regular-text" maxlength="64" />
				<p class="description" style="margin-top:4px;">Gebruik alleen letters, cijfers en koppeltekens.</p>
			</div>

			<div class="alc-gen-result" id="alc-gen-result">
				<label>Gegenereerde tekstlink</label>
				<div class="alc-gen-result-url" id="alc-gen-result-url"></div>
				<div class="alc-gen-source" id="alc-gen-source"></div>
				<div class="alc-gen-copy">
					<button type="button" class="button" id="alc-gen-copy-btn">Kopieer link</button>
					<span class="alc-gen-copied" id="alc-gen-copied">✓ Gekopieerd!</span>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var siteId       = <?php echo wp_json_encode( $site_id ); ?>;
			var materialUrls = <?php echo wp_json_encode( (object) $material_urls ); ?>;
			var campaigns    = <?php echo wp_json_encode( $campaign_list ); ?>;

			var elCamp   = document.getElementById('alc-gen-campaign');
			var elUrl    = document.getElementById('alc-gen-url');
			var elRef    = document.getElementById('alc-gen-ref');
			var elHint   = document.getElementById('alc-gen-url-hint');
			var elResult = document.getElementById('alc-gen-result');
			var elOut    = document.getElementById('alc-gen-result-url');
			var elSrc    = document.getElementById('alc-gen-source');
			var elCopy   = document.getElementById('alc-gen-copy-btn');
			var elCopied = document.getElementById('alc-gen-copied');

			// Hostname zonder www., null bij ongeldige URL
			function hostname(url) {
				try { return new URL(url).hostname.replace(/^www\./, ''); }
				catch(e) { return null; }
			}

			// Zoek campagne-ID op basis van domein van de doel-URL
			function detectCampaign(destUrl) {
				var host = hostname(destUrl);
				if (!host) return null;
				for (var i = 0; i < campaigns.length; i++) {
					var c = campaigns[i];
					if (!c.url) continue;
					var ch = hostname(c.url);
					if (ch && (host === ch || host.endsWith('.' + ch) || ch.endsWith('.' + host))) {
						return c.id;
					}
				}
				return null;
			}

			function showHint(msg, type) {
				elHint.textContent    = msg;
				elHint.className      = 'alc-gen-url-hint ' + type;
				elHint.style.display  = 'block';
			}
			function hideHint() { elHint.style.display = 'none'; }

			function generate() {
				var campaignId = elCamp.value;
				if (!campaignId) { elResult.style.display = 'none'; return; }

				var destUrl = elUrl.value.trim();
				var ref     = elRef.value.trim().replace(/[^a-zA-Z0-9\-_]/g, '');
				var baseUrl = materialUrls[campaignId] || null;
				var link, source;

				if (baseUrl) {
					link = baseUrl + ref;
					if (destUrl) {
						try {
							var parsed = new URL(destUrl);
							link += '&r=' + encodeURIComponent(parsed.pathname + parsed.search + parsed.hash);
						} catch(e) {}
					}
					source = '✓ Merchant-domein (via TradeTracker materiaal API)';
				} else {
					link = 'https://tc.tradetracker.net/?c=' + encodeURIComponent(campaignId)
					     + '&m=12&a=' + encodeURIComponent(siteId);
					if (ref)     link += '&r=' + encodeURIComponent(ref);
					if (destUrl) link += '&u=' + encodeURIComponent(destUrl);
					source = '↩ Fallback: tc.tradetracker.net (geen tekstmateriaal gevonden voor deze campagne)';
				}

				elOut.textContent      = link;
				elSrc.textContent      = source;
				elResult.style.display = 'block';
				elCopied.style.display = 'none';
			}

			// Auto-detect campagne bij URL-invoer
			elUrl.addEventListener('input', function() {
				var destUrl = elUrl.value.trim();

				if (!destUrl) { hideHint(); generate(); return; }

				var detectedId = detectCampaign(destUrl);

				if (detectedId) {
					if (elCamp.value !== detectedId) {
						elCamp.value = detectedId;
						var name = campaigns.find(function(c){ return c.id === detectedId; });
						showHint('✓ Campagne automatisch geselecteerd: ' + (name ? name.name : detectedId), 'success');
					} else {
						hideHint();
					}
				} else {
					// URL ingevuld maar domein herkend niet als campagne
					showHint('⚠ Domein herkend niet als een van je campagnes — controleer de selectie.', 'warning');
				}

				generate();
			});

			// Waarschuwing als handmatig een campagne wordt gekozen die niet bij de URL past
			elCamp.addEventListener('change', function() {
				var destUrl = elUrl.value.trim();
				if (!destUrl) { hideHint(); generate(); return; }

				var detectedId = detectCampaign(destUrl);
				if (detectedId && elCamp.value !== detectedId) {
					var name = campaigns.find(function(c){ return c.id === detectedId; });
					showHint('⚠ De ingevulde URL hoort bij een andere campagne' + (name ? ' (' + name.name + ')' : '') + '.', 'warning');
				} else {
					hideHint();
				}

				generate();
			});

			elRef.addEventListener('input', generate);

			elCopy.addEventListener('click', function() {
				var text = elOut.textContent;
				if (!text) return;
				if (navigator.clipboard) {
					navigator.clipboard.writeText(text).then(function() {
						elCopied.style.display = 'inline';
						setTimeout(function(){ elCopied.style.display = 'none'; }, 2000);
					});
				} else {
					var ta = document.createElement('textarea');
					ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
					document.body.appendChild(ta); ta.select(); document.execCommand('copy');
					document.body.removeChild(ta);
					elCopied.style.display = 'inline';
					setTimeout(function(){ elCopied.style.display = 'none'; }, 2000);
				}
			});
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Sales subtab
	// -------------------------------------------------------------------------

	private function render_sales_subtab(): void {
		$current_year  = (int) gmdate( 'Y' );
		$selected_year = isset( $_GET['jaar'] ) ? (int) $_GET['jaar'] : $current_year;
		$selected_year = max( 2015, min( $current_year, $selected_year ) );

		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
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
			<label for="tbmm_sales_jaar" style="font-weight:600;">Jaar:</label>
			<select id="tbmm_sales_jaar" name="jaar" onchange="this.form.submit()">
				<?php for ( $y = $current_year; $y >= 2020; $y-- ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $selected_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<?php
		// ── Uitbetaling sectie ────────────────────────────────────────────
		$pending     = $this->service->get_pending_commission( $site_id );
		$last_pmt    = $this->service->get_last_payment();
		$has_payout  = ! is_wp_error( $pending ) || ! is_wp_error( $last_pmt );

		if ( $has_payout ) :
			$pend_amount = ( ! is_wp_error( $pending ) ) ? (float) $pending['commission'] : 0.0;
			$pend_count  = ( ! is_wp_error( $pending ) ) ? (int) $pending['count'] : 0;
			?>
			<div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:14px 18px; margin-bottom:20px; max-width:600px;">
				<h4 style="margin:0 0 10px; font-size:13px; color:#1d2327;">Uitbetaling</h4>
				<table style="border-collapse:collapse; width:100%; font-size:13px;">
					<tr>
						<td style="padding:4px 0; color:#646970; width:200px;">Openstaand</td>
						<td style="padding:4px 0; font-weight:600;">
							<?php echo esc_html( '€ ' . number_format( $pend_amount, 2, ',', '.' ) ); ?>
							<?php if ( $pend_count > 0 ) : ?>
								<span style="color:#646970; font-weight:normal; font-size:12px;">(<?php echo esc_html( $pend_count ); ?> transacti<?php echo $pend_count === 1 ? 'e' : 'es'; ?>)</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( ! is_wp_error( $last_pmt ) && ! empty( $last_pmt ) ) : ?>
					<tr>
						<td style="padding:4px 0; color:#646970;">Laatste betaling</td>
						<td style="padding:4px 0;">
							€ <?php echo esc_html( number_format( (float) $last_pmt['amount'], 2, ',', '.' ) ); ?>
							<span style="color:#646970; font-size:12px;">op <?php echo esc_html( gmdate( 'd-m-Y', strtotime( $last_pmt['date'] ) ) ); ?></span>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ( $pend_amount > 0 && $pend_amount < 25 ) : ?>
					<tr>
						<td colspan="2" style="padding:6px 0 0; font-size:12px; color:#646970;">
							Minimumbedrag voor uitbetaling is € 25,00 — nog € <?php echo esc_html( number_format( 25 - $pend_amount, 2, ',', '.' ) ); ?> te gaan.
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
		<?php endif; ?>

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

	// -------------------------------------------------------------------------
	// Productfeed subtab
	// -------------------------------------------------------------------------

	private function render_productfeed_subtab(): void {
		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>Vul eerst de inloggegevens in via het tabblad Instellingen.</em></p>';
			return;
		}

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}

		$feeds_result = $this->service->get_feeds( $site_id, 'accepted' );
		if ( is_wp_error( $feeds_result ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $feeds_result->get_error_message() ) . '</p></div>';
			return;
		}

		// Normalize feeds to simple objects and extract unique campaigns
		$feeds_list  = [];
		$campaigns_seen = [];
		foreach ( $feeds_result as $feed ) {
			$f           = is_object( $feed ) ? $feed : (object) $feed;
			$feed_id     = (int) ( $f->ID ?? 0 );
			$feed_name   = (string) ( $f->name ?? '' );
			$camp        = is_object( $f->campaign ?? null ) ? $f->campaign : null;
			$camp_id     = $camp ? (string) ( $camp->ID ?? '' ) : '';
			$camp_name   = $camp ? (string) ( $camp->name ?? '' ) : '';

			if ( ! $feed_id ) {
				continue;
			}
			$feeds_list[] = [
				'id'           => $feed_id,
				'name'         => $feed_name,
				'campaign_id'  => $camp_id,
				'campaign_name'=> $camp_name,
			];
			if ( $camp_id && ! isset( $campaigns_seen[ $camp_id ] ) ) {
				$campaigns_seen[ $camp_id ] = $camp_name;
			}
		}
		asort( $campaigns_seen );

		$nonce = wp_create_nonce( 'tbmm_tt_feed_nonce' );
		?>

		<style>
			.alc-pf-filters { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
			.alc-pf-filters label { display:block; font-weight:600; font-size:12px; margin-bottom:3px; color:#1d2327; }
			.alc-pf-filters select, .alc-pf-filters input[type=text] { font-size:13px; }
			.alc-pf-results { margin-top:4px; }
			.alc-pf-table { border-collapse:collapse; width:100%; }
			.alc-pf-table th { background:#f6f7f7; font-weight:600; font-size:12px; padding:8px 10px; border:1px solid #e0e0e0; text-align:left; white-space:nowrap; }
			.alc-pf-table td { padding:8px 10px; border:1px solid #e0e0e0; vertical-align:top; font-size:13px; }
			.alc-pf-photo-col { width:110px; min-width:110px; max-width:110px; text-align:center; }
			.alc-pf-photo-col img { max-width:100px; max-height:100px; object-fit:contain; border-radius:3px; }
			.alc-pf-photo-col .alc-pf-no-img { width:100px; height:80px; background:#f0f0f1; border-radius:3px; display:flex; align-items:center; justify-content:center; font-size:11px; color:#bbb; margin:0 auto; }
			.alc-pf-name { font-weight:600; font-size:13px; margin-bottom:3px; }
			.alc-pf-cat  { font-size:11px; color:#646970; margin-bottom:4px; }
			.alc-pf-desc { font-size:12px; color:#3c434a; line-height:1.5; }
			.alc-pf-price-col { width:80px; text-align:right; font-weight:700; white-space:nowrap; }
			.alc-pf-action-col { width:90px; text-align:center; white-space:nowrap; }
			.alc-pf-campaign-col { width:140px; font-size:12px; color:#646970; }
			.alc-pf-status { padding:12px; font-size:13px; color:#646970; }
			.alc-pf-more { margin-top:12px; }
		</style>

		<div class="alc-pf-filters">
			<div>
				<label for="alc-pf-campaign">Campagne</label>
				<select id="alc-pf-campaign" style="min-width:180px;">
					<option value="">— Alle campagnes —</option>
					<?php foreach ( $campaigns_seen as $cid => $cname ) : ?>
					<option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $cname ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="alc-pf-feed">Productfeed</label>
				<select id="alc-pf-feed" style="min-width:220px;">
					<option value="0">— Alle productfeeds —</option>
					<?php foreach ( $feeds_list as $f ) : ?>
					<option value="<?php echo esc_attr( $f['id'] ); ?>"
					        data-campaign="<?php echo esc_attr( $f['campaign_id'] ); ?>">
						<?php echo esc_html( $f['name'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="alc-pf-search">Zoekwoord</label>
				<input type="text" id="alc-pf-search" placeholder="bijv. vogelvoer" class="regular-text" style="width:200px;" />
			</div>
			<div>
				<label for="alc-pf-perpage">Per pagina</label>
				<select id="alc-pf-perpage">
					<option value="10">10</option>
					<option value="25" selected>25</option>
					<option value="50">50</option>
					<option value="100">100</option>
					<option value="500">500</option>
				</select>
			</div>
			<div>
				<label>&nbsp;</label>
				<button type="button" id="alc-pf-search-btn" class="button button-primary">Zoeken</button>
			</div>
		</div>

		<div id="alc-pf-results" class="alc-pf-results"></div>

		<script>
		(function() {
			var nonce     = <?php echo wp_json_encode( $nonce ); ?>;
			var feedsList = <?php echo wp_json_encode( $feeds_list ); ?>;

			var elCampaign  = document.getElementById('alc-pf-campaign');
			var elFeed      = document.getElementById('alc-pf-feed');
			var elSearch    = document.getElementById('alc-pf-search');
			var elPerPage   = document.getElementById('alc-pf-perpage');
			var elSearchBtn = document.getElementById('alc-pf-search-btn');
			var elResults   = document.getElementById('alc-pf-results');

			var currentPage  = 1;
			var lastFeedId   = 0;
			var lastSearch   = '';
			var lastPerPage  = 25;

			// Filter feeds dropdown when campaign changes
			elCampaign.addEventListener('change', function() {
				var selectedCamp = elCampaign.value;
				var prevFeed     = elFeed.value;
				var opts         = elFeed.querySelectorAll('option');

				opts.forEach(function(opt) {
					if (opt.value === '0') return; // keep "Alle productfeeds"
					var campAttr = opt.getAttribute('data-campaign');
					opt.style.display = (!selectedCamp || campAttr === selectedCamp) ? '' : 'none';
				});

				// Reset to "Alle" if selected feed is from a different campaign
				if (prevFeed && prevFeed !== '0') {
					var selOpt = elFeed.querySelector('option[value="' + prevFeed + '"]');
					if (selOpt && selOpt.style.display === 'none') {
						elFeed.value = '0';
					}
				}
			});

			// Allow Enter key in search field
			elSearch.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') { currentPage = 1; doSearch(); }
			});

			elSearchBtn.addEventListener('click', function() {
				currentPage = 1;
				doSearch();
			});

			function doSearch() {
				var feedId  = elFeed.value || '0';
				var search  = elSearch.value.trim();
				var perPage = parseInt(elPerPage.value, 10);

				// Cross-feed search requires a keyword
				if (feedId === '0' && !search) {
					elResults.innerHTML = '<p class="alc-pf-status">Voer een zoekwoord in om over alle feeds te zoeken.</p>';
					return;
				}

				currentPage = 1;
				lastFeedId  = feedId;
				lastSearch  = search;
				lastPerPage = perPage;

				elSearchBtn.disabled    = true;
				elSearchBtn.textContent = 'Laden…';
				elResults.innerHTML     = '<p class="alc-pf-status">Producten ophalen…</p>';

				fetchProducts(feedId, search, perPage, currentPage, elCampaign.value, function(resp) {
					elSearchBtn.disabled    = false;
					elSearchBtn.textContent = 'Zoeken';

					if (!resp.success) {
						elResults.innerHTML = '<p class="alc-pf-status" style="color:#d63638;">' + escHtml(resp.data.message || 'Fout bij ophalen.') + '</p>';
						return;
					}

					var data = resp.data;
					if (!data.products || data.products.length === 0) {
						elResults.innerHTML = '<p class="alc-pf-status">Geen producten gevonden.</p>';
						return;
					}

					var showFeedCol = data.all_feeds && data.products.some(function(p){ return p.feed_name; });
					elResults.innerHTML = buildTable(data.products, showFeedCol);

					if (data.all_feeds && data.total_found > data.products.length) {
						var note = document.createElement('p');
						note.style.cssText = 'font-size:12px; color:#646970; margin-top:8px;';
						note.textContent = data.total_found + ' resultaten gevonden — selecteer een specifieke feed voor meer resultaten.';
						elResults.appendChild(note);
					}

					if (data.has_more) {
						var moreWrap = document.createElement('div');
						moreWrap.className = 'alc-pf-more';
						moreWrap.innerHTML = '<button type="button" class="button" id="alc-pf-more-btn">Volgende ' + escHtml(perPage) + ' laden →</button>';
						elResults.appendChild(moreWrap);
						document.getElementById('alc-pf-more-btn').addEventListener('click', function() {
							currentPage++;
							loadMore();
						});
					}
				});
			}

			function loadMore() {
				var moreBtn = document.getElementById('alc-pf-more-btn');
				if (moreBtn) { moreBtn.disabled = true; moreBtn.textContent = 'Laden…'; }

				fetchProducts(lastFeedId, lastSearch, lastPerPage, currentPage, elCampaign.value, function(resp) {
					if (moreBtn && moreBtn.parentNode) moreBtn.parentNode.remove();

					if (!resp.success || !resp.data.products || resp.data.products.length === 0) return;

					var data  = resp.data;
					var tbody = elResults.querySelector('tbody');
					if (tbody) {
						var tmp = document.createElement('table');
						tmp.innerHTML = '<tbody>' + buildRows(data.products, false) + '</tbody>';
						Array.from(tmp.querySelector('tbody').children).forEach(function(row) {
							tbody.appendChild(row);
						});
					}

					if (data.has_more) {
						var moreWrap2 = document.createElement('div');
						moreWrap2.className = 'alc-pf-more';
						moreWrap2.innerHTML = '<button type="button" class="button" id="alc-pf-more-btn">Volgende ' + escHtml(lastPerPage) + ' laden →</button>';
						elResults.appendChild(moreWrap2);
						document.getElementById('alc-pf-more-btn').addEventListener('click', function() {
							currentPage++;
							loadMore();
						});
					}
				});
			}

			function fetchProducts(feedId, search, perPage, page, campaignId, callback) {
				var fd = new FormData();
				fd.append('action',      'tbmm_feed_search');
				fd.append('nonce',       nonce);
				fd.append('feed_id',     feedId);
				fd.append('search',      search);
				fd.append('per_page',    perPage);
				fd.append('page',        page);
				fd.append('campaign_id', campaignId || '');

				fetch(ajaxurl, { method: 'POST', body: fd })
					.then(function(r) { return r.json(); })
					.then(callback)
					.catch(function() {
						callback({ success: false, data: { message: 'Verbindingsfout.' } });
					});
			}

			function buildTable(products, showFeedCol) {
				return '<table class="alc-pf-table">'
					+ '<thead><tr>'
					+ '<th class="alc-pf-photo-col">Foto</th>'
					+ '<th>Naam &amp; beschrijving</th>'
					+ (showFeedCol ? '<th class="alc-pf-campaign-col">Feed</th>' : '<th class="alc-pf-campaign-col">Categorie</th>')
					+ '<th class="alc-pf-price-col">Prijs</th>'
					+ '<th class="alc-pf-action-col">Actie</th>'
					+ '</tr></thead>'
					+ '<tbody>' + buildRows(products, showFeedCol) + '</tbody>'
					+ '</table>';
			}

			function buildRows(products, showFeedCol) {
				return products.map(function(p) {
					var img = p.image
						? '<img src="' + escAttr(p.image) + '" alt="" loading="lazy" />'
						: '<div class="alc-pf-no-img">Geen foto</div>';

					var price  = p.price ? '€&nbsp;' + escHtml(p.price) : '—';
					var action = p.url
						? '<a href="' + escAttr(p.url) + '" target="_blank" rel="noopener" class="button button-small">↗ Bekijk</a>'
						: '—';
					var midCol = showFeedCol ? escHtml(p.feed_name) : escHtml(p.category);

					return '<tr>'
						+ '<td class="alc-pf-photo-col">' + img + '</td>'
						+ '<td>'
						+   '<div class="alc-pf-name">' + escHtml(p.name) + '</div>'
						+   (p.desc ? '<div class="alc-pf-desc">' + escHtml(p.desc) + '</div>' : '')
						+ '</td>'
						+ '<td class="alc-pf-campaign-col">' + midCol + '</td>'
						+ '<td class="alc-pf-price-col">' + price + '</td>'
						+ '<td class="alc-pf-action-col">' + action + '</td>'
						+ '</tr>';
				}).join('');
			}

			function escHtml(str) {
				var d = document.createElement('div');
				d.appendChild(document.createTextNode(String(str || '')));
				return d.innerHTML;
			}

			function escAttr(str) {
				return String(str || '').replace(/"/g, '&quot;');
			}
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// FONQ.nl subtab
	// -------------------------------------------------------------------------

	private function render_fonq_subtab(): void {
		$links = $this->ta_service->get_links_by_destination( 'fonq' );
		$nonce = wp_create_nonce( 'tbmm_orphan_nonce' );
		?>
		<style>
			.alc-fonq-articles-row td { padding:6px 12px !important; background:#f9f9f9; }
			.alc-fonq-articles-list  { margin:4px 0 0; padding:0; list-style:none; }
			.alc-fonq-articles-list li { margin-bottom:3px; font-size:12px; }
		</style>

		<?php if ( empty( $links ) ) : ?>
		<p style="color:#646970; font-size:13px;">Geen actieve FONQ.nl links gevonden in ThirstyAffiliates.</p>
		<?php return; endif; ?>

		<p class="alc-ta-summary">
			<strong><?php echo esc_html( count( $links ) ); ?></strong> FONQ.nl links gevonden in ThirstyAffiliates.
		</p>

		<table class="alc-ta-table">
			<thead>
				<tr>
					<th class="alc-rank-cell">#</th>
					<th>Link naam</th>
					<th>Destination URL</th>
					<th style="width:130px;">Bewerk</th>
					<th style="width:160px;">Zoek in artikelen</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $links as $i => $row ) :
				$edit_url = admin_url( 'post.php?post=' . (int) $row->ID . '&action=edit' );
				$dest_url = $row->destination_url ?: '';
				$slug     = $row->post_name;
			?>
			<tr>
				<td class="alc-rank-cell"><?php echo esc_html( $i + 1 ); ?></td>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank"
					   style="text-decoration:none; color:inherit;">
						<?php echo esc_html( $row->post_title ); ?>
					</a>
				</td>
				<td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
					<?php if ( $dest_url ) : ?>
					<a href="<?php echo esc_url( $dest_url ); ?>" target="_blank"
					   title="<?php echo esc_attr( $dest_url ); ?>"
					   style="font-size:12px; color:#646970; text-decoration:none;">
						<?php echo esc_html( $dest_url ); ?>
					</a>
					<?php else : ?>
					<span style="color:#b0b0b0; font-size:12px;">—</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank"
					   class="button button-small">✎ Bewerk in TA</a>
				</td>
				<td>
					<button class="button button-small alc-fonq-find-btn"
					        data-slug="<?php echo esc_attr( $slug ); ?>"
					        data-row="<?php echo esc_attr( $i ); ?>">
						Zoek in artikelen
					</button>
				</td>
			</tr>
			<tr class="alc-fonq-articles-row" id="alc-fonq-ar-<?php echo esc_attr( $i ); ?>" style="display:none;">
				<td colspan="5"></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		(function() {
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;

			document.querySelectorAll('.alc-fonq-find-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var slug  = btn.dataset.slug;
					var rowId = btn.dataset.row;
					var arRow = document.getElementById('alc-fonq-ar-' + rowId);
					var cell  = arRow ? arRow.querySelector('td') : null;

					if (!slug || !arRow || !cell) return;

					if (arRow.style.display !== 'none') {
						arRow.style.display = 'none';
						btn.textContent = 'Zoek in artikelen';
						return;
					}

					btn.disabled    = true;
					btn.textContent = 'Zoeken…';

					var data = new FormData();
					data.append('action', 'tbmm_orphan_find_articles');
					data.append('nonce',  nonce);
					data.append('slug',   slug);

					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function(r) { return r.json(); })
						.then(function(resp) {
							btn.disabled = false;
							if (resp.success && resp.data.articles && resp.data.articles.length > 0) {
								var html = '<ul class="alc-fonq-articles-list">';
								resp.data.articles.forEach(function(a) {
									html += '<li>'
										+ '<a href="' + escHtml(a.edit_url) + '" target="_blank">'
										+ escHtml(a.post_title)
										+ '</a></li>';
								});
								html += '</ul>';
								cell.innerHTML  = html;
								btn.textContent = 'Verberg ▲';
							} else {
								cell.innerHTML  = '<em style="font-size:12px; color:#646970;">Niet gevonden in gepubliceerde artikelen.</em>';
								btn.textContent = 'Verberg ▲';
							}
							arRow.style.display = '';
						})
						.catch(function() {
							btn.disabled    = false;
							btn.textContent = 'Zoek in artikelen';
						});
				});
			});

			function escHtml(str) {
				var d = document.createElement('div');
				d.appendChild(document.createTextNode(String(str)));
				return d.innerHTML;
			}
		})();
		</script>
		<?php
	}
}
