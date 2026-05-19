<?php

namespace TuinenBalkon\AffiliateLinkChecker\Admin;

class MenuService {

	private ScanPage $scan_page;

	public function __construct( ScanPage $scan_page ) {
		$this->scan_page = $scan_page;
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	public function add_admin_menu(): void {
		add_menu_page(
			'Affiliate Link Checker',
			'Link Checker',
			'manage_options',
			'affiliate-link-checker',
			[ $this->scan_page, 'render' ],
			'dashicons-search',
			26
		);
	}
}
