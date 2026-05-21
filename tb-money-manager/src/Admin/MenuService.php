<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

class MenuService {

	private ScanPage $scan_page;

	public function __construct( ScanPage $scan_page ) {
		$this->scan_page = $scan_page;
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_bol_assets' ] );
	}

	public function enqueue_bol_assets( string $hook ): void {
		if ( 'toplevel_page_tb-money-manager' !== $hook ) {
			return;
		}
		if ( ( $_GET['tab'] ?? '' ) !== 'bol' ) {
			return;
		}

		$assets_url  = plugins_url( 'assets/', TBMM_FILE );
		$assets_path = TBMM_PATH . 'assets/';

		wp_enqueue_style(
			'alc-bol-admin-styles',
			$assets_url . 'bol-admin-styles.css',
			array(),
			filemtime( $assets_path . 'bol-admin-styles.css' )
		);

		wp_register_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true );

		wp_enqueue_script(
			'alc-bol-admin-settings',
			$assets_url . 'bol-admin-settings.js',
			array( 'jquery', 'chart-js' ),
			filemtime( $assets_path . 'bol-admin-settings.js' ),
			true
		);

		wp_localize_script(
			'alc-bol-admin-settings',
			'bol_settings_params',
			array(
				'nonce'                => wp_create_nonce( 'bol_test_connection_nonce' ),
				'marketing_test_nonce' => wp_create_nonce( 'tbmm_bol_marketing_test_nonce' ),
				'chart_nonce'          => wp_create_nonce( 'bol_fetch_chart_data_nonce' ),
				'clear_cache_nonce'    => wp_create_nonce( 'bol_clear_cache_nonce' ),
			)
		);
	}

	public function add_admin_menu(): void {
		add_menu_page(
			'TB Money Manager',
			'TB Money Manager',
			'manage_options',
			'tb-money-manager',
			[ $this->scan_page, 'render' ],
			'dashicons-money-alt',
			24
		);

		// Submenu-items per tab — vervangt het automatisch aangemaakte duplicaat
		global $submenu;
		$base = 'admin.php?page=tb-money-manager';
		$submenu['tb-money-manager'] = [
			[ 'Link Scanner',      'manage_options', $base . '&tab=scanner'      ],
			[ 'TradeTracker',      'manage_options', $base . '&tab=tradetracker' ],
			[ 'ThirstyAffiliates', 'manage_options', $base . '&tab=ta'           ],
			[ 'Bol.com',           'manage_options', $base . '&tab=bol'          ],
			[ 'Google',            'manage_options', $base . '&tab=google'        ],
			[ 'Tools',             'manage_options', $base . '&tab=tools'        ],
		];
	}
}
