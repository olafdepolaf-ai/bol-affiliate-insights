<?php
namespace TuinenBalkon\BolAffiliateInsights;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class for Bol Affiliate Insights.
 *
 * This class handles the initialization of the plugin, loading of necessary files,
 * and setting up hooks for WordPress actions and filters. It acts as the central
 * point for the plugin's functionality.
 */
class Plugin {

    private static $instance;

    private $api_auth_service;
    private $api_client;
    private $report_data_service;
    private $settings_service;
    private $menu_service;
    private $ajax_handler_service;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        error_log('Bol Affiliate Insights: Plugin constructor called.');
        $this->api_auth_service = new \TuinenBalkon\BolAffiliateInsights\Service\ApiAuthService();
        $this->api_client = new \TuinenBalkon\BolAffiliateInsights\Service\ApiClient($this->api_auth_service);
        $this->report_data_service = new \TuinenBalkon\BolAffiliateInsights\Service\ReportDataService($this->api_client);
        $this->settings_service = new \TuinenBalkon\BolAffiliateInsights\Service\SettingsService();
        $settings_page = new \TuinenBalkon\BolAffiliateInsights\Admin\SettingsPage();
        $this->menu_service = new \TuinenBalkon\BolAffiliateInsights\Admin\MenuService($settings_page);
        $this->ajax_handler_service = new \TuinenBalkon\BolAffiliateInsights\Admin\AjaxHandlerService($this->report_data_service, $this->api_auth_service, $this->api_client);
        add_filter( 'plugin_action_links_' . plugin_basename( BOL_AFFILIATE_INSIGHTS_FILE ), array( $this, 'add_settings_link' ) );
        error_log('Bol Affiliate Insights: Plugin constructor finished.');
    }

    public function add_settings_link( $links ) {
        error_log('Bol Affiliate Insights: settings_link filter called.');
        $url = esc_url( admin_url( 'admin.php?page=bol-affiliate-insights&tab=settings' ) );
        $settings_link = '<a href="' . $url . '">' . __( 'Settings', 'bol-affiliate-insights' ) . '</a>';
        $links[] = $settings_link;
        return $links;
    }

    public function get_api_client() {
        return $this->api_client;
    }
}
