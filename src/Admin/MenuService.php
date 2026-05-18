<?php
namespace TuinenBalkon\BolAffiliateInsights\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class MenuService {
    private $settings_page;

    public function __construct(SettingsPage $settings_page) {
        $this->settings_page = $settings_page;
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function enqueue_admin_assets($hook) {
        // Check if we are on the correct admin page.
        if ( 'toplevel_page_bol-affiliate-insights' !== $hook ) {
            return;
        }

        // Enqueue Style
        wp_enqueue_style(
            'bol-admin-styles',
            plugins_url( 'assets/css/admin-styles.css', BOL_AFFILIATE_INSIGHTS_FILE ),
            array(),
            filemtime( BOL_AFFILIATE_INSIGHTS_PATH . 'assets/css/admin-styles.css' )
        );

        // Enqueue Script
        wp_enqueue_script(
            'bol-admin-settings',
            plugins_url( 'assets/js/admin-settings.js', BOL_AFFILIATE_INSIGHTS_FILE ),
            array( 'jquery', 'chart-js' ),
            filemtime( BOL_AFFILIATE_INSIGHTS_PATH . 'assets/js/admin-settings.js' ),
            true
        );

        // Register Chart.js separately to ensure it's loaded
        wp_register_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true );

        // Localize script with nonce
        wp_localize_script(
            'bol-admin-settings',
            'bol_settings_params',
            array(
                'nonce'             => wp_create_nonce( 'bol_test_connection_nonce' ),
                'chart_nonce'       => wp_create_nonce( 'bol_fetch_chart_data_nonce' ),
                'clear_cache_nonce' => wp_create_nonce( 'bol_clear_cache_nonce' ),
            )
        );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Bol Affiliate Insights',
            'Bol Insights',
            'manage_options',
            'bol-affiliate-insights',
            array( $this->settings_page, 'render_settings_page' ),
            plugins_url( 'assets/images/bol-logo.png', BOL_AFFILIATE_INSIGHTS_FILE ),
            25
        );
    }
}
