<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

class SettingsTab {

	private BolTab           $bol_tab;
	private TradeTrackerTab  $tt_tab;

	public function __construct( BolTab $bol_tab, TradeTrackerTab $tt_tab ) {
		$this->bol_tab = $bol_tab;
		$this->tt_tab  = $tt_tab;
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=settings' );
		$subtab   = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'bol';

		$subtabs = array(
			'bol'          => __( 'Bol.com', 'tbmm' ),
			'tradetracker' => __( 'TradeTracker', 'tbmm' ),
		);

		if ( ! array_key_exists( $subtab, $subtabs ) ) {
			$subtab = 'bol';
		}
		?>
		<style>
			.tbmm-settings-subtab-nav { display:flex; align-items:flex-end; gap:4px; margin-bottom:20px; border-bottom:1px solid #c3c4c7; padding-bottom:0; }
			.tbmm-settings-subtab-nav a { display:inline-block; padding:6px 14px; text-decoration:none; font-size:13px; color:#2271b1; border:1px solid transparent; border-bottom:none; border-radius:3px 3px 0 0; margin-bottom:-1px; }
			.tbmm-settings-subtab-nav a:hover { background:#f0f0f1; color:#135e96; }
			.tbmm-settings-subtab-nav a.active { background:#fff; border-color:#c3c4c7; color:#1d2327; font-weight:600; }
		</style>
		<nav class="tbmm-settings-subtab-nav">
			<?php foreach ( $subtabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $base_url . '&subtab=' . $slug ); ?>"
			   class="<?php echo $subtab === $slug ? 'active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>

		<?php
		if ( $subtab === 'tradetracker' ) {
			$this->tt_tab->render_settings();
		} else {
			$this->bol_tab->render_settings();
		}
	}
}
