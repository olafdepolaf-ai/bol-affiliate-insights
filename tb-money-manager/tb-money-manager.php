<?php
/**
 * Plugin Name: TB Money Manager
 * Plugin URI: https://github.com/olafdepolaf-ai/bol-affiliate-insights
 * Description: Beheert affiliate inkomstenbronnen van tuinenbalkon.nl — rapportage en linkbeheer voor Bol.com, TradeTracker en andere partnerprogramma's.
 * Version: 0.2.43
 * Update URI: https://github.com/olafdepolaf-ai/bol-affiliate-insights
 * Author: Olaf Lemmers
 * Author URI: https://github.com/olafdepolaf-ai
 */

namespace TuinenBalkon\TBMoneyManager;

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'TBMM_FILE', __FILE__ );
define( 'TBMM_PATH', plugin_dir_path( TBMM_FILE ) );

spl_autoload_register( function ( $class ) {
	$prefix   = 'TuinenBalkon\\TBMoneyManager\\';
	$base_dir = TBMM_PATH . 'src/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

register_activation_hook( TBMM_FILE, [ \TuinenBalkon\TBMoneyManager\Installer::class, 'activate' ] );
register_uninstall_hook( TBMM_FILE, [ \TuinenBalkon\TBMoneyManager\Installer::class, 'uninstall' ] );

Plugin::get_instance();
