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
