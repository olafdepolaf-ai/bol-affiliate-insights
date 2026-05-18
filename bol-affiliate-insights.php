<?php
/**
 * Plugin Name:       Bol Affiliate Insights
 * Plugin URI:        https://www.tuinenbalkon.nl
 * Description:       Connects Bol.com Partner Insights with your WordPress environment. Fetches and displays commission, click, and revenue data from the Bol.com Affiliate Reporting API.
 * Version:           0.3.0
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

namespace TuinenBalkon\BolAffiliateInsights;

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

// Register activation and deactivation hooks.
register_activation_hook(BOL_AFFILIATE_INSIGHTS_FILE, array('TuinenBalkon\BolAffiliateInsights\Plugin', 'activate'));
register_deactivation_hook(BOL_AFFILIATE_INSIGHTS_FILE, array('TuinenBalkon\BolAffiliateInsights\Plugin', 'deactivate'));


// Instantiate the plugin to get it running.
// This ensures that the plugin's functionality is initialized and hooks are registered.
Plugin::get_instance();
?>