<?php
/**
 * Plugin Name:       Bol Affiliate Insights
 * Plugin URI:        https://www.tuinenbalkon.nl
 * Description:       Connects Bol.com Partner Insights with your WordPress environment. Fetches and displays commission, click, and revenue data from the Bol.com Affiliate Reporting API.
 * Version:           0.1.0
 * Author:            Olaf Lemmers
 * Author URI:        https://www.tuinenbalkon.nl
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bol-affiliate-insights
 * Domain Path:       /languages
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

if ( ! class_exists( 'Bol_Affiliate_Insights_Plugin' ) ) {
    /**
     * Main plugin class for Bol Affiliate Insights.
     *
     * This class handles the initialization of the plugin, loading of necessary files,
     * and provides a central point for accessing plugin services like the API client.
     * It follows a singleton pattern to ensure only one instance exists.
     */
    class Bol_Affiliate_Insights_Plugin {

        /**
         * The single instance of the class.
         *
         * @var Bol_Affiliate_Insights_Plugin|null
         */
        private static $instance;

        /**
         * Instance of the API Authentication Service.
         *
         * @var Bol_API_Auth_Service|null
         */
        private $auth_service_instance;

        /**
         * Instance of the API Client Service.
         *
         * @var Bol_API_Client|null
         */
        private $api_client_instance;

        /**
         * Ensures only one instance of the plugin class is loaded or can be loaded.
         *
         * @return Bol_Affiliate_Insights_Plugin The single instance of the class.
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor for the plugin.
         *
         * Private to prevent direct object creation.
         * Initializes required files and services.
         */
        private function __construct() {
            $this->includes(); // Load necessary class files.
            // Instantiate the authentication service, critical for API interactions.
            $this->auth_service_instance = new Bol_API_Auth_Service();
            // Example: add_action( 'plugins_loaded', array( $this, 'init' ) ); // Hook for further initialization.
        }

        /**
         * Includes all necessary PHP class files for the plugin.
         *
         * This method is called early in the plugin's lifecycle to ensure all
         * dependencies are available.
         */
        private function includes() {
            require_once BOL_AFFILIATE_INSIGHTS_PATH . 'includes/class-bol-affiliate-insights-settings.php';
            require_once BOL_AFFILIATE_INSIGHTS_PATH . 'includes/class-bol-api-auth-service.php';
            require_once BOL_AFFILIATE_INSIGHTS_PATH . 'includes/class-bol-api-client.php';
            require_once BOL_AFFILIATE_INSIGHTS_PATH . 'includes/class-bol-orders-list-table.php';
            require_once BOL_AFFILIATE_INSIGHTS_PATH . 'includes/class-bol-commission-revenue-list-table.php';
            require_once BOL_AFFILIATE_INSIGHTS_PATH . 'includes/class-bol-promotion-methods-list-table.php';
            
            // Instantiate settings class to ensure its hooks are registered.
            new Bol_Affiliate_Insights_Settings();
        }

        /**
         * Initializes the plugin.
         *
         * This method is intended for actions that should run after all plugins are loaded,
         * such as loading the plugin text domain for localization or checking for dependencies.
         * Currently, it's a placeholder for such future enhancements.
         */
        public function init() {
            // Example: Load plugin textdomain for translation.
            // load_plugin_textdomain( 'bol-affiliate-insights', false, dirname( plugin_basename( BOL_AFFILIATE_INSIGHTS_FILE ) ) . '/languages/' );
            
            // Example: Check for required dependencies or run update routines.
        }

        /**
         * Get the API client instance.
         *
         * Implements lazy loading for the API client. It ensures that the API client
         * is instantiated only when it's actually needed, and that the authentication
         * service is available.
         *
         * @return Bol_API_Client The initialized API client instance.
         */
        public function get_api_client() {
            if ( null === $this->api_client_instance ) {
                // Ensure auth service is available, though it should be by constructor.
                if ( null === $this->auth_service_instance ) {
                     $this->auth_service_instance = new Bol_API_Auth_Service();
                }
                $this->api_client_instance = new Bol_API_Client( $this->auth_service_instance );
            }
            return $this->api_client_instance;
        }

        // Other methods will be added later
    }

    // Instantiate the plugin to get it running.
    Bol_Affiliate_Insights_Plugin::get_instance();
}
?>
