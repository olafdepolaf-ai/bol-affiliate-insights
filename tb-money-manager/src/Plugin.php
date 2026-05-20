<?php

namespace TuinenBalkon\TBMoneyManager;

use TuinenBalkon\TBMoneyManager\Admin\AjaxHandlerService;
use TuinenBalkon\TBMoneyManager\Admin\BolTab;
use TuinenBalkon\TBMoneyManager\Admin\MenuService;
use TuinenBalkon\TBMoneyManager\Admin\ScanPage;
use TuinenBalkon\TBMoneyManager\Admin\TATab;
use TuinenBalkon\TBMoneyManager\Admin\TradeTrackerTab;
use TuinenBalkon\TBMoneyManager\Service\LinkScanner;
use TuinenBalkon\TBMoneyManager\Service\OrphanedLinkScanner;
use TuinenBalkon\TBMoneyManager\Service\PostFinder;
use TuinenBalkon\TBMoneyManager\Service\ScanCacheService;
use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;
use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class Plugin {

	private static ?Plugin $instance = null;

	private function __construct() {
		new UpdateChecker( TBMM_FILE );

		$post_finder        = new PostFinder();
		$link_scanner       = new LinkScanner( $post_finder );
		$tt_service         = new TradeTrackerService();
		$ta_service         = new ThirstyAffiliatesService();
		$orphaned_scanner   = new OrphanedLinkScanner();
		$scan_cache         = new ScanCacheService();
		$tt_tab             = new TradeTrackerTab( $tt_service, $ta_service, $orphaned_scanner );
		$ta_tab             = new TATab( $ta_service, $orphaned_scanner, $scan_cache );
		$bol_tab            = new BolTab();
		$scan_page          = new ScanPage( $link_scanner, $tt_tab, $ta_tab, $bol_tab );
		new MenuService( $scan_page );
		new AjaxHandlerService( $link_scanner, $orphaned_scanner, $scan_cache, $ta_service, $tt_service );

		add_filter(
			'plugin_action_links_' . plugin_basename( TBMM_FILE ),
			function( array $links ): array {
				$settings = '<a href="' . admin_url( 'admin.php?page=tb-money-manager' ) . '">Instellingen</a>';
				array_unshift( $links, $settings );
				return $links;
			}
		);
	}

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
