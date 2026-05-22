<?php

namespace TuinenBalkon\TBMoneyManager\Admin\TradeTracker;

use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class SettingsSubtab {

	private TradeTrackerService $service;

	public function __construct( TradeTrackerService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		$notice = '';

		if ( isset( $_POST['tbmm_tt_save_settings'] ) && check_admin_referer( 'tbmm_tt_settings', 'tbmm_tt_nonce' ) ) {
			update_option( 'tbmm_tt_customer_id', sanitize_text_field( wp_unslash( $_POST['tbmm_tt_customer_id'] ?? '' ) ) );
			update_option( 'tbmm_tt_access_key',  sanitize_text_field( wp_unslash( $_POST['tbmm_tt_access_key'] ?? '' ) ) );
			$this->service->clear_cache();
			$notice = '<div class="notice notice-success inline"><p>' . esc_html__( 'Instellingen opgeslagen en cache gewist.', 'tbmm' ) . '</p></div>';
		}

		if ( isset( $_POST['tbmm_tt_clear_cache'] ) && check_admin_referer( 'tbmm_tt_settings', 'tbmm_tt_nonce' ) ) {
			$this->service->clear_cache();
			$notice = '<div class="notice notice-success inline"><p>' . esc_html__( 'Cache gewist.', 'tbmm' ) . '</p></div>';
		}

		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		$has_creds   = ! empty( $customer_id ) && ! empty( $access_key );

		echo wp_kses_post( $notice );
		?>

		<form method="post">
			<?php wp_nonce_field( 'tbmm_tt_settings', 'tbmm_tt_nonce' ); ?>
			<h3 style="margin-top:0;"><?php esc_html_e( 'API inloggegevens', 'tbmm' ); ?></h3>
			<table class="form-table" style="max-width:560px;">
				<tr>
					<th scope="row"><label for="tbmm_tt_customer_id"><?php esc_html_e( 'Klant-ID', 'tbmm' ); ?></label></th>
					<td><input type="text" id="tbmm_tt_customer_id" name="tbmm_tt_customer_id"
						value="<?php echo esc_attr( $customer_id ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'bijv. 26710', 'tbmm' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="tbmm_tt_access_key"><?php esc_html_e( 'Toegangssleutel', 'tbmm' ); ?></label></th>
					<td><input type="password" id="tbmm_tt_access_key" name="tbmm_tt_access_key"
						value="<?php echo esc_attr( $access_key ); ?>"
						class="regular-text" /></td>
				</tr>
			</table>
			<button type="submit" name="tbmm_tt_save_settings" class="button button-primary"><?php esc_html_e( 'Opslaan', 'tbmm' ); ?></button>
			<?php if ( $has_creds ) : ?>
			<button type="submit" name="tbmm_tt_clear_cache" class="button" style="margin-left:8px;"><?php esc_html_e( 'Cache vernieuwen', 'tbmm' ); ?></button>
			<?php endif; ?>
		</form>

		<?php if ( ! $has_creds ) : ?>
		<p style="margin-top:16px;"><em><?php esc_html_e( 'Vul de inloggegevens in om data op te halen.', 'tbmm' ); ?></em></p>
		<?php return; endif;

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline" style="margin-top:16px;"><p><strong>'
				. esc_html__( 'Verbindingsfout:', 'tbmm' )
				. '</strong> ' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}

		$sites = $this->service->get_affiliate_sites();
		if ( is_wp_error( $sites ) ) {
			echo '<div class="notice notice-error inline" style="margin-top:16px;"><p><strong>'
				. esc_html__( 'Verbindingsfout:', 'tbmm' )
				. '</strong> ' . esc_html( $sites->get_error_message() ) . '</p></div>';
			return;
		}

		$this->render_account_info( $sites );
		$this->render_campaigns( $site_id );
	}

	private function render_account_info( array $sites ): void {
		?>
		<h3 style="margin-top:24px;"><?php esc_html_e( 'Affiliate sites', 'tbmm' ); ?></h3>
		<table class="widefat striped" style="max-width:800px; margin-bottom:24px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Naam', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'URL', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Type', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tbmm' ); ?></th>
				</tr>
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
		<h3>
			<?php
			/* translators: %d = number of subscribed campaigns */
			printf( esc_html__( 'Geabonneerde campagnes (%d)', 'tbmm' ), count( $campaigns ) );
			?>
		</h3>
		<?php if ( empty( $campaigns ) ) : ?>
		<p><em><?php esc_html_e( 'Geen geabonneerde campagnes.', 'tbmm' ); ?></em></p>
		<?php return; endif; ?>
		<table class="widefat striped" style="max-width:900px; margin-bottom:24px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Naam', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Categorie', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Commissie type', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tbmm' ); ?></th>
					<th></th>
				</tr>
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
}
