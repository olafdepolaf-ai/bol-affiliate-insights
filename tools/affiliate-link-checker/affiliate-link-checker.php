<?php
/**
 * Plugin Name: Affiliate Link Checker
 * Description: Scant ThirstyAffiliates destination URLs op 404s en serverfouten.
 * Version: 0.1.7
 * Update URI: https://github.com/olafdepolaf-ai/bol-affiliate-insights
 * Author: OlafL
 */

namespace TuinenBalkon\AffiliateLinkChecker;

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ALC_FILE', __FILE__ );
define( 'ALC_PATH', plugin_dir_path( ALC_FILE ) );

spl_autoload_register( function ( $class ) {
	$prefix   = 'TuinenBalkon\\AffiliateLinkChecker\\';
	$base_dir = ALC_PATH . 'src/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

Plugin::get_instance();
