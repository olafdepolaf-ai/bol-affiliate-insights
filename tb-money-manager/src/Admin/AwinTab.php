<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Admin\Awin\DashboardSubtab;
use TuinenBalkon\TBMoneyManager\Admin\Awin\SettingsSubtab;
use TuinenBalkon\TBMoneyManager\Service\AwinService;

class AwinTab {

	private AwinService    $service;
	private DashboardSubtab $dashboard;
	private SettingsSubtab  $settings;

	public function __construct() {
		$this->service   = new AwinService();
		$this->dashboard = new DashboardSubtab( $this->service );
		$this->settings  = new SettingsSubtab( $this->service );
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=awin' );
		$subtab   = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'dashboard';

		$subtabs = [
			'dashboard' => __( 'Dashboard', 'tbmm' ),
		];
		?>
		<div class="tbmm-subnav-wrap">
		<nav class="tbmm-subnav">
			<?php foreach ( $subtabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $base_url . '&subtab=' . $slug ); ?>"
			   class="<?php echo $subtab === $slug ? 'tbmm-subnav-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>
		</div>

		<?php
		$this->dashboard->render();
	}

	public function render_settings(): void {
		$this->settings->render();
	}
}
