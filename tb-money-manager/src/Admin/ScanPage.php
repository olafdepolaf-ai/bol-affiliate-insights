<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

class ScanPage {

	private TradeTrackerTab $tt_tab;
	private TATab           $ta_tab;
	private BolTab          $bol_tab;
	private GoogleTab       $google_tab;
	private AwinTab         $awin_tab;
	private SettingsTab     $settings_tab;

	public function __construct( TradeTrackerTab $tt_tab, TATab $ta_tab, BolTab $bol_tab, GoogleTab $google_tab, AwinTab $awin_tab, SettingsTab $settings_tab ) {
		$this->tt_tab       = $tt_tab;
		$this->ta_tab       = $ta_tab;
		$this->bol_tab      = $bol_tab;
		$this->google_tab   = $google_tab;
		$this->awin_tab     = $awin_tab;
		$this->settings_tab = $settings_tab;
	}

	public function render(): void {
		$update_notice   = '';
		$update_redirect = '';
		if ( isset( $_GET['tbmm_check_updates'] ) && check_admin_referer( 'tbmm_check_updates' ) ) {
			delete_transient( 'tbmm_github_update' );
			delete_site_transient( 'update_plugins' );
			if ( ! function_exists( 'wp_update_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/update.php';
			}
			wp_update_plugins();
			$update_redirect = esc_url( remove_query_arg( array( 'tbmm_check_updates', '_wpnonce' ) ) );
			$update_notice   = '<div class="notice notice-success"><p>' . esc_html__( 'Cache gewist. WordPress controleert nu op updates.', 'tbmm' ) . '</p></div>';
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'bol';
		$page_url    = admin_url( 'admin.php?page=tb-money-manager' );

		$main_tabs = array(
			'bol'          => __( 'Bol.com', 'tbmm' ),
			'tradetracker' => __( 'TradeTracker', 'tbmm' ),
			'awin'         => __( 'Awin', 'tbmm' ),
			'google'       => __( 'Google', 'tbmm' ),
			'ta'           => __( 'ThirstyAffiliates', 'tbmm' ),
		);

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( TBMM_FILE );
		$current_ver = $plugin_data['Version'] ?? '?';
		$check_url   = wp_nonce_url(
			add_query_arg( 'tbmm_check_updates', '1', $page_url . '&tab=' . $current_tab ),
			'tbmm_check_updates'
		);
		?>
		<div class="wrap">
			<h1 style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
				<?php esc_html_e( 'TB Money Manager', 'tbmm' ); ?>
				<span style="font-size:13px; font-weight:400; color:#646970;">v<?php echo esc_html( $current_ver ); ?></span>
				<a href="<?php echo esc_url( $check_url ); ?>"
				   class="button button-small"
				   style="font-size:12px;">
					↻ <?php esc_html_e( 'Controleer op updates', 'tbmm' ); ?>
				</a>
			</h1>

			<?php echo wp_kses_post( $update_notice ); ?>
			<?php if ( $update_redirect ) : ?>
			<script>setTimeout(function(){ window.location.href = <?php echo wp_json_encode( $update_redirect ); ?>; }, 1200);</script>
			<?php endif; ?>

<div class="tbmm-nav-wrap">
				<nav class="tbmm-nav" role="navigation">
					<?php foreach ( $main_tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( $page_url . '&tab=' . $slug ); ?>"
					   class="<?php echo $current_tab === $slug ? 'tbmm-nav-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
					<?php endforeach; ?>
					<span class="tbmm-nav-spacer"></span>
					<a href="<?php echo esc_url( $page_url . '&tab=settings' ); ?>"
					   class="tbmm-nav-settings <?php echo $current_tab === 'settings' ? 'tbmm-nav-active' : ''; ?>">
						⚙ <?php esc_html_e( 'Instellingen', 'tbmm' ); ?>
					</a>
				</nav>
			</div>

			<?php if ( $current_tab === 'tradetracker' ) : ?>
				<?php $this->tt_tab->render(); ?>
			<?php elseif ( $current_tab === 'awin' ) : ?>
				<?php $this->awin_tab->render(); ?>
			<?php elseif ( $current_tab === 'ta' ) : ?>
				<?php $this->ta_tab->render(); ?>
			<?php elseif ( $current_tab === 'google' ) : ?>
				<?php $this->google_tab->render(); ?>
			<?php elseif ( $current_tab === 'settings' ) : ?>
				<?php $this->settings_tab->render(); ?>
			<?php else : ?>
				<?php $this->bol_tab->render(); ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
