<?php
/**
 * Plugin Name:       Bol Affiliate Insights
 * Plugin URI:        https://www.tuinenbalkon.nl
 * Description:       Connects Bol.com Partner Insights with your WordPress environment. Fetches and displays commission, click, and revenue data from the Bol.com Affiliate Reporting API.
 * Version:           0.1.4
 * Author:            Olaf Lemmers
 * Author URI:        https://www.tuinenbalkon.nl
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bol-affiliate-insights
 * Domain Path:       /languages
 */

/**
 * Main plugin file for Bol Affiliate Insights.
 *
 * This file is responsible for initializing the plugin, loading necessary files,
 * and setting up hooks for WordPress actions and filters. It acts as the central
 * point for the plugin's functionality.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants for file paths.
if ( ! defined( 'BOL_AFFILIATE_INSIGHTS_FILE' ) ) {
    /**
     * The main plugin file.
     * Used for referencing the plugin file path.
     */
    define( 'BOL_AFFILIATE_INSIGHTS_FILE', __FILE__ );
}
if ( ! defined( 'BOL_AFFILIATE_INSIGHTS_PATH' ) ) {
    /**
     * The root plugin path.
     * Used for including files.
     */
    define( 'BOL_AFFILIATE_INSIGHTS_PATH', plugin_dir_path( BOL_AFFILIATE_INSIGHTS_FILE ) );
}

/**
 * Adds a settings link to the plugin action links.
 *
 * This function is hooked into 'plugin_action_links_' . plugin_basename(__FILE__).
 * It adds a direct link to the plugin's settings page on the WordPress Plugins admin page.
 *
 * @param array $links An array of plugin action links.
 * @return array An array of plugin action links with the new settings link.
 */
function bol_affiliate_insights_settings_link( array $links ) {
    $url = esc_url( admin_url( 'admin.php?page=bol-affiliate-insights&tab=settings' ) );
    $settings_link = '<a href="' . $url . '">' . __( 'Settings', 'bol-affiliate-insights' ) . '</a>';
    $links[] = $settings_link;
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( BOL_AFFILIATE_INSIGHTS_FILE ), 'bol_affiliate_insights_settings_link' );

spl_autoload_register(function ($class) {
    // project-specific namespace prefix
    $prefix = 'TuinenBalkon\\BolAffiliateInsights\\';

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/src/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

namespace TuinenBalkon\BolAffiliateInsights;

if ( ! class_exists( 'TuinenBalkon\BolAffiliateInsights\Plugin' ) ) {
    /**
     * Main plugin class for Bol Affiliate Insights.
     *
     * This class handles the initialization of the plugin, loading of necessary files,
     * and provides a central point for accessing plugin services like the API client.
     * It follows a singleton pattern to ensure only one instance exists.
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
            $this->api_auth_service = new \TuinenBalkon\BolAffiliateInsights\Service\ApiAuthService();
            $this->api_client = new \TuinenBalkon\BolAffiliateInsights\Service\ApiClient($this->api_auth_service);
            $this->report_data_service = new \TuinenBalkon\BolAffiliateInsights\Service\ReportDataService($this->api_client);
            $this->settings_service = new \TuinenBalkon\BolAffiliateInsights\Service\SettingsService();
            $settings_page = new \TuinenBalkon\BolAffiliateInsights\Admin\SettingsPage();
            $this->menu_service = new \TuinenBalkon\BolAffiliateInsights\Admin\MenuService($settings_page);
            $this->ajax_handler_service = new \TuinenBalkon\BolAffiliateInsights\Admin\AjaxHandlerService($this->report_data_service, $this->api_auth_service, $this->api_client);
        }

        public function get_api_client() {
            return $this->api_client;
        }
    }

    // Instantiate the plugin to get it running.
    // This ensures that the plugin's functionality is initialized and hooks are registered.
    Plugin::get_instance();
}
?>
