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

    /** @var \TuinenBalkon\BolAffiliateInsights\AffiliateLink\AffiliateLinkAdapterInterface */
    private $affiliate_link_adapter;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        \TuinenBalkon\BolAffiliateInsights\Service\Logger::debug( 'Plugin activated.' );
        // Activation code here. For example, creating database tables.
    }

    public static function deactivate() {
        \TuinenBalkon\BolAffiliateInsights\Service\Logger::debug( 'Plugin deactivated.' );
        // Deactivation code here. For example, cleaning up options.
    }

    private function __construct() {
        $this->api_auth_service      = new \TuinenBalkon\BolAffiliateInsights\Service\ApiAuthService();
        $this->api_client            = new \TuinenBalkon\BolAffiliateInsights\Service\ApiClient( $this->api_auth_service );
        $this->report_data_service   = new \TuinenBalkon\BolAffiliateInsights\Service\ReportDataService( $this->api_client );
        $this->settings_service      = new \TuinenBalkon\BolAffiliateInsights\Service\SettingsService();
        $settings_page               = new \TuinenBalkon\BolAffiliateInsights\Admin\SettingsPage();
        $this->menu_service          = new \TuinenBalkon\BolAffiliateInsights\Admin\MenuService( $settings_page );
        $this->ajax_handler_service  = new \TuinenBalkon\BolAffiliateInsights\Admin\AjaxHandlerService( $this->report_data_service, $this->api_auth_service, $this->api_client );
        add_filter( 'plugin_action_links_' . plugin_basename( BOL_AFFILIATE_INSIGHTS_FILE ), array( $this, 'add_settings_link' ) );
    }

    /**
     * Picks the right affiliate link adapter based on which plugin is active.
     * Add more adapters here when switching to a different affiliate link plugin.
     */
    private function resolve_affiliate_adapter(): \TuinenBalkon\BolAffiliateInsights\AffiliateLink\AffiliateLinkAdapterInterface {
        $adapters = array(
            new \TuinenBalkon\BolAffiliateInsights\AffiliateLink\ThirstyAffiliatesAdapter(),
        );
        foreach ( $adapters as $adapter ) {
            if ( $adapter->is_available() ) {
                return $adapter;
            }
        }
        return new \TuinenBalkon\BolAffiliateInsights\AffiliateLink\NullAdapter();
    }

    public function add_settings_link( $links ) {
        \TuinenBalkon\BolAffiliateInsights\Service\Logger::debug( 'settings_link filter called.' );
        $url = esc_url( admin_url( 'admin.php?page=bol-affiliate-insights&tab=settings' ) );
        $settings_link = '<a href="' . $url . '">' . __( 'Settings', 'bol-affiliate-insights' ) . '</a>';
        $links[] = $settings_link;
        return $links;
    }

    public function get_api_client() {
        return $this->api_client;
    }

    public function get_report_data_service() {
        return $this->report_data_service;
    }

    /**
     * Returns the active affiliate link adapter.
     * Use this in other services/pages — never import affiliate plugin classes directly.
     *
     * @return \TuinenBalkon\BolAffiliateInsights\AffiliateLink\AffiliateLinkAdapterInterface
     */
    /**
     * Returns the active affiliate link adapter, resolved lazily after init
     * so post types registered by other plugins are guaranteed to exist.
     *
     * @return \TuinenBalkon\BolAffiliateInsights\AffiliateLink\AffiliateLinkAdapterInterface
     */
    public function get_affiliate_link_adapter(): \TuinenBalkon\BolAffiliateInsights\AffiliateLink\AffiliateLinkAdapterInterface {
        if ( null === $this->affiliate_link_adapter ) {
            $this->affiliate_link_adapter = $this->resolve_affiliate_adapter();
        }
        return $this->affiliate_link_adapter;
    }
}
