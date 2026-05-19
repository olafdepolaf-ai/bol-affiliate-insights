<?php

namespace TuinenBalkon\AffiliateLinkChecker\Admin;

use TuinenBalkon\AffiliateLinkChecker\Service\TradeTrackerService;

class TradeTrackerTab {

	private TradeTrackerService $service;

	public function __construct( TradeTrackerService $service ) {
		$this->service = $service;
	}

	public function render(): void {
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

		$customer_id  = get_option( 'alc_tt_customer_id', '' );
		$access_key   = get_option( 'alc_tt_access_key', '' );
		$has_creds    = ! empty( $customer_id ) && ! empty( $access_key );

		echo wp_kses_post( $notice );
		?>

		<form method="post" style="margin-bottom:24px;">
			<?php wp_nonce_field( 'alc_tt_settings', 'alc_tt_nonce' ); ?>
			<h2 style="margin-top:0;">Inloggegevens</h2>
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
		<p><em>Vul de inloggegevens in om data op te halen.</em></p>
		<?php return; endif; ?>

		<?php
		$sites = $this->service->get_affiliate_sites();

		if ( is_wp_error( $sites ) ) {
			echo '<div class="notice notice-error inline"><p><strong>Fout:</strong> ' . esc_html( $sites->get_error_message() ) . '</p></div>';
			return;
		}

		if ( empty( $sites ) ) {
			echo '<p><em>Geen affiliate sites gevonden.</em></p>';
			return;
		}

		$primary_site = is_array( $sites ) ? reset( $sites ) : $sites;
		$site_id      = is_object( $primary_site ) ? $primary_site->ID : ( $primary_site['ID'] ?? '' );

		$this->render_account_info( $sites );
		$this->render_campaigns( $site_id );
		$this->render_report( $site_id );
	}

	private function render_account_info( array $sites ): void {
		?>
		<h2>Account / Affiliate sites</h2>
		<table class="widefat striped" style="max-width:800px; margin-bottom:24px;">
			<thead>
				<tr>
					<th>ID</th>
					<th>Naam</th>
					<th>URL</th>
					<th>Type</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sites as $site ) :
					$s = (object) $site;
				?>
				<tr>
					<td><?php echo esc_html( $s->ID ?? '—' ); ?></td>
					<td><?php echo esc_html( $s->name ?? '—' ); ?></td>
					<td>
						<?php if ( ! empty( $s->URL ) ) : ?>
						<a href="<?php echo esc_url( $s->URL ); ?>" target="_blank"><?php echo esc_html( $s->URL ); ?></a>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td><?php echo esc_html( $s->type->name ?? $s->type ?? '—' ); ?></td>
					<td><?php echo esc_html( $s->status ?? '—' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_campaigns( string $site_id ): void {
		if ( empty( $site_id ) ) {
			return;
		}

		$campaigns = $this->service->get_campaigns( $site_id );

		if ( is_wp_error( $campaigns ) ) {
			echo '<div class="notice notice-error inline"><p><strong>Campagnes fout:</strong> ' . esc_html( $campaigns->get_error_message() ) . '</p></div>';
			return;
		}
		?>
		<h2>Geabonneerde campagnes (<?php echo count( $campaigns ); ?>)</h2>
		<?php if ( empty( $campaigns ) ) : ?>
		<p><em>Geen geabonneerde campagnes gevonden.</em></p>
		<?php return; endif; ?>
		<table class="widefat striped" style="max-width:900px; margin-bottom:24px;">
			<thead>
				<tr>
					<th>ID</th>
					<th>Naam</th>
					<th>Categorie</th>
					<th>Commissie type</th>
					<th>Status</th>
					<th>URL</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $campaign ) :
					$c = (object) $campaign;
				?>
				<tr>
					<td><?php echo esc_html( $c->ID ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->name ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->category->name ?? $c->category ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->commissionType ?? '—' ); ?></td>
					<td><?php echo esc_html( $c->assignmentStatus ?? $c->status ?? '—' ); ?></td>
					<td>
						<?php if ( ! empty( $c->URL ) ) : ?>
						<a href="<?php echo esc_url( $c->URL ); ?>" target="_blank">↗</a>
						<?php else : ?>—<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_report( string $site_id ): void {
		if ( empty( $site_id ) ) {
			return;
		}

		$start  = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
		$end    = gmdate( 'Y-m-t', strtotime( 'last day of last month' ) );
		$report = $this->service->get_report_last_month( $site_id );

		if ( is_wp_error( $report ) ) {
			echo '<div class="notice notice-error inline"><p><strong>Rapport fout:</strong> ' . esc_html( $report->get_error_message() ) . '</p></div>';
			return;
		}
		?>
		<h2>Rapport vorige maand (<?php echo esc_html( $start ); ?> t/m <?php echo esc_html( $end ); ?>)</h2>
		<?php if ( empty( $report ) ) : ?>
		<p><em>Geen rapportdata beschikbaar.</em></p>
		<?php return; endif; ?>

		<?php
		$r = (object) $report;

		$metrics = [
			'impressions'         => 'Impressies',
			'clicks'              => 'Kliks',
			'leads'               => 'Leads',
			'sales'               => 'Sales',
			'revenue'             => 'Omzet (adverteerder)',
			'commission'          => 'Commissie (jouw verdienste)',
			'openLeadsCommission' => 'Open leads commissie',
			'openSalesCommission' => 'Open sales commissie',
			'fixedCommission'     => 'Vaste commissie',
		];
		?>
		<table class="widefat striped" style="max-width:480px; margin-bottom:24px;">
			<thead>
				<tr><th>Metriek</th><th>Waarde</th></tr>
			</thead>
			<tbody>
				<?php foreach ( $metrics as $key => $label ) :
					if ( ! isset( $r->$key ) ) continue;
					$val = $r->$key;
					$formatted = is_numeric( $val ) && strpos( $key, 'ommission' ) !== false || $key === 'revenue'
						? '€ ' . number_format( (float) $val, 2, ',', '.' )
						: number_format( (float) $val, 0, ',', '.' );
				?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><?php echo esc_html( $formatted ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		// Raw debug dump for PoC — shows all fields returned by API
		echo '<details style="margin-bottom:24px;"><summary style="cursor:pointer; color:#646970;">Ruwe API response (debug)</summary>';
		echo '<pre style="background:#f6f7f7; padding:12px; overflow:auto; font-size:12px;">';
		echo esc_html( print_r( $report, true ) );
		echo '</pre></details>';
	}
}
