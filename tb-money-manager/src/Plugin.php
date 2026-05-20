<?php

namespace TuinenBalkon\TBMoneyManager;

use TuinenBalkon\TBMoneyManager\Admin\AjaxHandlerService;
use TuinenBalkon\TBMoneyManager\Admin\BolTab;
use TuinenBalkon\TBMoneyManager\Admin\ToolsTab;
use TuinenBalkon\TBMoneyManager\Admin\DashboardWidget;
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
use TuinenBalkon\TBMoneyManager\Service\UnmanagedLinkScanner;

class Plugin {

	private static ?Plugin $instance = null;

	private function __construct() {
		// UpdateChecker werkt update-detectie af voor alle gebruikers (WP core vereist dit).
		new UpdateChecker( TBMM_FILE );

		// Al het overige initialiseert alleen voor ingelogde administrators.
		add_action( 'init', [ $this, 'init_for_admins' ] );
		add_action( 'admin_init', [ $this, 'maybe_upgrade_db' ] );
	}

	public function maybe_upgrade_db(): void {
		$installed = (int) get_option( 'tbmm_db_version', 0 );
		if ( $installed >= 2 ) {
			return;
		}
		Installer::activate();
		update_option( 'tbmm_db_version', 2, false );
	}

	public function init_for_admins(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		new DashboardWidget();

		$post_finder      = new PostFinder();
		$link_scanner     = new LinkScanner( $post_finder );
		$tt_service       = new TradeTrackerService();
		$ta_service       = new ThirstyAffiliatesService();
		$orphaned_scanner = new OrphanedLinkScanner();
		$scan_cache       = new ScanCacheService();
		$tt_tab           = new TradeTrackerTab( $tt_service, $ta_service, $orphaned_scanner );
		$ta_tab           = new TATab( $ta_service, $orphaned_scanner, $scan_cache );
		$bol_tab          = new BolTab();
		$unmanaged        = new UnmanagedLinkScanner();
		$tools_tab        = new ToolsTab( $unmanaged );
		$scan_page        = new ScanPage( $link_scanner, $tt_tab, $ta_tab, $bol_tab, $tools_tab );
		new MenuService( $scan_page );
		new AjaxHandlerService( $link_scanner, $orphaned_scanner, $scan_cache, $ta_service, $tt_service, $unmanaged );

		add_filter(
			'plugin_action_links_' . plugin_basename( TBMM_FILE ),
			function( array $links ): array {
				$links[] = '<a href="' . admin_url( 'admin.php?page=tb-money-manager' ) . '">Instellingen</a>';
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
