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
			'dashboard'          => __( 'Dashboard', 'tbmm' ),
			'orders'             => __( 'Orders', 'tbmm' ),
			'commission_revenue' => __( 'Commissie & Omzet', 'tbmm' ),
			'promotion_methods'  => __( 'Promotiemethoden', 'tbmm' ),
			'analysis'           => __( 'Analyse', 'tbmm' ),
			'drop_analysis'      => __( 'Klik-drop', 'tbmm' ),
			'affiliate_links'    => __( 'Affiliate links', 'tbmm' ),
			'link_generator'     => __( 'Linkgenerator', 'tbmm' ),
		);
		?>
		<div class="tbmm-subnav-wrap">
		<nav class="tbmm-subnav">
			<?php foreach ( $left_subtabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $base_url . '&subtab=' . $slug ); ?>"
			   class="<?php echo $subtab === $slug ? 'tbmm-subnav-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>
		</div>

		<?php
		if ( ! array_key_exists( $subtab, $left_subtabs ) ) {
			$subtab = 'dashboard';
		}
		$this->settings_page->render_content( $subtab );
	}

	public function render_settings(): void {
		$this->settings_page->render_content( 'settings' );
	}
}
