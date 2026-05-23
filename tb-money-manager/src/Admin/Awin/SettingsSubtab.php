<?php

namespace TuinenBalkon\TBMoneyManager\Admin\Awin;

use TuinenBalkon\TBMoneyManager\Service\AwinService;

class SettingsSubtab {

	private AwinService $service;

	public function __construct( AwinService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		$notice = '';

		if ( isset( $_POST['tbmm_awin_save'] ) && check_admin_referer( 'tbmm_awin_settings', 'tbmm_awin_nonce' ) ) {
			update_option( 'tbmm_awin_api_token',    sanitize_text_field( wp_unslash( $_POST['tbmm_awin_api_token']    ?? '' ) ) );
			update_option( 'tbmm_awin_publisher_id', sanitize_text_field( wp_unslash( $_POST['tbmm_awin_publisher_id'] ?? '' ) ) );
			$this->service->clear_cache();
			delete_option( 'tbmm_awin_connection_status' );
			$notice = '<div class="notice notice-success inline"><p>' . esc_html__( 'Instellingen opgeslagen en cache gewist.', 'tbmm' ) . '</p></div>';
		}

		if ( isset( $_POST['tbmm_awin_check'] ) && check_admin_referer( 'tbmm_awin_settings', 'tbmm_awin_nonce' ) ) {
			$notice = $this->do_connection_check();
		}

		$token     = $this->service->get_api_token();
		$pub_id    = $this->service->get_publisher_id();
		$has_creds = ! empty( $token ) && ! empty( $pub_id );

		echo wp_kses_post( $notice );
		?>
		<form method="post">
			<?php wp_nonce_field( 'tbmm_awin_settings', 'tbmm_awin_nonce' ); ?>
			<table class="form-table" style="max-width:560px;">
				<tr>
					<th scope="row"><label for="tbmm_awin_api_token"><?php esc_html_e( 'API-token', 'tbmm' ); ?></label></th>
					<td>
						<input type="password" id="tbmm_awin_api_token" name="tbmm_awin_api_token"
							value="<?php echo esc_attr( $token ); ?>"
							class="regular-text" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Te vinden via Awin → rechtsboven menu → API Credentials.', 'tbmm' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tbmm_awin_publisher_id"><?php esc_html_e( 'Publisher ID', 'tbmm' ); ?></label></th>
					<td>
						<input type="text" id="tbmm_awin_publisher_id" name="tbmm_awin_publisher_id"
							value="<?php echo esc_attr( $pub_id ); ?>"
							class="regular-text" placeholder="bijv. 362499" />
					</td>
				</tr>
			</table>
			<button type="submit" name="tbmm_awin_save" class="button button-primary"><?php esc_html_e( 'Opslaan', 'tbmm' ); ?></button>
			<?php if ( $has_creds ) : ?>
			<button type="submit" name="tbmm_awin_check" class="button" style="margin-left:8px;"><?php esc_html_e( 'Verbinding testen', 'tbmm' ); ?></button>
			<?php endif; ?>
		</form>

		<?php if ( ! $has_creds ) : ?>
		<p style="margin-top:16px;"><em><?php esc_html_e( 'Vul de API-token en Publisher ID in om verbinding te maken.', 'tbmm' ); ?></em></p>
		<?php return; endif;

		$this->render_stored_status();
	}

	private function do_connection_check(): string {
		$profile = $this->service->get_profile();

		if ( is_wp_error( $profile ) ) {
			delete_option( 'tbmm_awin_connection_status' );
			return '<div class="notice notice-error inline"><p><strong>'
				. esc_html__( 'Verbindingsfout:', 'tbmm' )
				. '</strong> ' . esc_html( $profile->get_error_message() ) . '</p></div>';
		}

		update_option( 'tbmm_awin_connection_status', [
			'publisher_id'    => $profile['id'] ?? $this->service->get_publisher_id(),
			'programme_count' => $profile['programmeCount'] ?? '—',
			'checked_at'      => time(),
		], false );

		return '';
	}

	private function render_stored_status(): void {
		$status = get_option( 'tbmm_awin_connection_status', false );

		if ( ! $status ) {
			echo '<p style="margin-top:16px; color:#646970;"><em>'
				. esc_html__( 'Verbinding nog niet getest. Klik op "Verbinding testen" om te controleren.', 'tbmm' )
				. '</em></p>';
			return;
		}

		$checked_at = isset( $status['checked_at'] )
			? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['checked_at'] )
			: '—';
		?>
		<div style="margin-top:20px; background:#edfaef; border:1px solid #b8e6be; border-radius:4px; padding:12px 16px; max-width:560px;">
			<p style="margin:0 0 8px; font-weight:600; color:#00a32a;">✓ <?php esc_html_e( 'Verbinding actief', 'tbmm' ); ?></p>
			<table style="border-collapse:collapse; font-size:13px; width:100%;">
				<tr>
					<td style="padding:3px 0; color:#646970; width:130px;"><?php esc_html_e( 'Publisher ID', 'tbmm' ); ?></td>
					<td style="padding:3px 0;"><?php echo esc_html( (string) ( $status['publisher_id'] ?? '—' ) ); ?></td>
				</tr>
				<tr>
					<td style="padding:3px 0; color:#646970;"><?php esc_html_e( 'Programmes', 'tbmm' ); ?></td>
					<td style="padding:3px 0;"><?php echo esc_html( (string) ( $status['programme_count'] ?? '—' ) ); ?></td>
				</tr>
				<tr>
					<td style="padding:3px 0; color:#646970;"><?php esc_html_e( 'Gecontroleerd op', 'tbmm' ); ?></td>
					<td style="padding:3px 0;"><?php echo esc_html( $checked_at ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}
}
