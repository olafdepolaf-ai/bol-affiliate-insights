<?php

namespace TuinenBalkon\AffiliateLinkChecker;

use TuinenBalkon\AffiliateLinkChecker\Admin\AjaxHandlerService;
use TuinenBalkon\AffiliateLinkChecker\Admin\MenuService;
use TuinenBalkon\AffiliateLinkChecker\Admin\ScanPage;
use TuinenBalkon\AffiliateLinkChecker\Admin\TradeTrackerTab;
use TuinenBalkon\AffiliateLinkChecker\Service\LinkScanner;
use TuinenBalkon\AffiliateLinkChecker\Service\PostFinder;
use TuinenBalkon\AffiliateLinkChecker\Service\TradeTrackerService;

class Plugin {

	private static ?Plugin $instance = null;

	private function __construct() {
		new UpdateChecker( ALC_FILE );

		$post_finder        = new PostFinder();
		$link_scanner       = new LinkScanner( $post_finder );
		$tt_service         = new TradeTrackerService();
		$tt_tab             = new TradeTrackerTab( $tt_service );
		$scan_page          = new ScanPage( $link_scanner, $tt_tab );
		new MenuService( $scan_page );
		new AjaxHandlerService( $link_scanner );
	}

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
