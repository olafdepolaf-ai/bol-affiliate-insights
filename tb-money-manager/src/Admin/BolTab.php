<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Bol\Admin\AjaxHandlerService;
use TuinenBalkon\TBMoneyManager\Bol\Admin\SettingsPage;
use TuinenBalkon\TBMoneyManager\Bol\AffiliateLink\NullAdapter;
use TuinenBalkon\TBMoneyManager\Bol\AffiliateLink\ThirstyAffiliatesAdapter;
use TuinenBalkon\TBMoneyManager\Bol\Service\ApiAuthService;
use TuinenBalkon\TBMoneyManager\Bol\Service\ApiClient;
use TuinenBalkon\TBMoneyManager\Bol\Service\ReportDataService;
use TuinenBalkon\TBMoneyManager\Bol\Service\SettingsService;

class BolTab {

	private SettingsPage $settings_page;

	public function __construct() {
		$api_auth    = new ApiAuthService();
		$api_client  = new ApiClient( $api_auth );
		$report_data = new ReportDataService( $api_client );
		$adapter     = $this->resolve_adapter();

		new SettingsService( $api_client );
		new AjaxHandlerService( $report_data, $api_auth, $api_client );

		$this->settings_page = new SettingsPage( $api_client, $report_data, $adapter );
	}

	private function resolve_adapter() {
		$ta = new ThirstyAffiliatesAdapter();
		if ( $ta->is_available() ) {
			return $ta;
		}
		return new NullAdapter();
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=bol' );
		$subtab   = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'dashboard';

		$left_subtabs = array(
			'dashboard'          => 'Dashboard',
			'orders'             => 'Orders',
			'commission_revenue' => 'Commission & Revenue',
			'promotion_methods'  => 'Promotion Methods',
			'analysis'           => 'Analyse',
			'affiliate_links'    => 'Affiliate Links',
		);
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
				&#9881; Instellingen
			</a>
		</nav>

		<?php
		$valid_subtabs = array_merge( array_keys( $left_subtabs ), array( 'settings' ) );
		if ( ! in_array( $subtab, $valid_subtabs, true ) ) {
			$subtab = 'dashboard';
		}
		$this->settings_page->render_content( $subtab );
	}
}
