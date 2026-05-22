<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Admin\TradeTracker\FonqSubtab;
use TuinenBalkon\TBMoneyManager\Admin\TradeTracker\KliksSubtab;
use TuinenBalkon\TBMoneyManager\Admin\TradeTracker\LinkgeneratorSubtab;
use TuinenBalkon\TBMoneyManager\Admin\TradeTracker\ProductfeedSubtab;
use TuinenBalkon\TBMoneyManager\Admin\TradeTracker\RapportSubtab;
use TuinenBalkon\TBMoneyManager\Admin\TradeTracker\SalesSubtab;
use TuinenBalkon\TBMoneyManager\Admin\TradeTracker\SettingsSubtab;
use TuinenBalkon\TBMoneyManager\Service\OrphanedLinkScanner;
use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;
use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class TradeTrackerTab {

	private TradeTrackerService $service;
	private SalesSubtab         $sales;
	private KliksSubtab         $kliks;
	private RapportSubtab       $rapport;
	private LinkgeneratorSubtab $linkgenerator;
	private ProductfeedSubtab   $productfeed;
	private FonqSubtab          $fonq;
	private SettingsSubtab      $settings;

	public function __construct(
		TradeTrackerService $service,
		ThirstyAffiliatesService $ta_service,
		OrphanedLinkScanner $orphaned_scanner
	) {
		$this->service       = $service;
		$this->sales         = new SalesSubtab( $service );
		$this->kliks         = new KliksSubtab( $service );
		$this->rapport       = new RapportSubtab( $service );
		$this->linkgenerator = new LinkgeneratorSubtab( $service );
		$this->productfeed   = new ProductfeedSubtab( $service );
		$this->fonq          = new FonqSubtab( $ta_service );
		$this->settings      = new SettingsSubtab( $service );

		add_action( 'admin_init', [ $this, 'handle_cache_flush' ] );
	}

	public function handle_cache_flush(): void {
		if ( ( $_GET['page'] ?? '' ) !== 'tb-money-manager' ) {
			return;
		}

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			return;
		}

		$year = isset( $_GET['jaar'] ) ? max( 2015, min( (int) gmdate( 'Y' ), (int) $_GET['jaar'] ) ) : (int) gmdate( 'Y' );

		if ( isset( $_GET['tbmm_flush_clicks'] )
			&& wp_verify_nonce( sanitize_key( $_GET['tbmm_flush_clicks'] ), 'tbmm_flush_clicks_' . $year )
		) {
			$this->service->clear_clicks_cache( $site_id, $year );
			wp_safe_redirect( admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=kliks&jaar=' . $year . '&cache_cleared=1' ) );
			exit;
		}

		if ( isset( $_GET['tbmm_flush_rapport'] )
			&& wp_verify_nonce( sanitize_key( $_GET['tbmm_flush_rapport'] ), 'tbmm_flush_rapport_' . $year )
		) {
			$this->service->clear_report_cache( $site_id, $year );
			wp_safe_redirect( admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker&subtab=rapport&jaar=' . $year . '&cache_cleared=1' ) );
			exit;
		}
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=tradetracker' );
		$subtab   = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'sales';

		$subtabs = [
			'sales'         => __( 'Sales', 'tbmm' ),
			'kliks'         => __( 'Kliks', 'tbmm' ),
			'rapport'       => __( 'Rapport', 'tbmm' ),
			'linkgenerator' => __( 'Linkgenerator', 'tbmm' ),
			'fonq'          => __( 'FONQ.nl', 'tbmm' ),
			'productfeed'   => __( 'Productbrowser', 'tbmm' ),
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
		match ( $subtab ) {
			'kliks'         => $this->kliks->render(),
			'rapport'       => $this->rapport->render(),
			'linkgenerator' => $this->linkgenerator->render(),
			'fonq'          => $this->fonq->render(),
			'productfeed'   => $this->productfeed->render(),
			default         => $this->sales->render(),
		};
	}

	public function render_settings(): void {
		$this->settings->render();
	}
}
