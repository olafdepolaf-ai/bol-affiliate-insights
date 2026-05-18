<?php
namespace TuinenBalkon\BolAffiliateInsights\Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Logger {

    /**
     * Check whether debug logging is enabled in plugin settings.
     *
     * @return bool
     */
    public static function is_debug_enabled() {
        return (bool) get_option( 'bol_affiliate_insights_debug_enabled', false );
    }

    /**
     * Log a debug-level message if debug is enabled.
     *
     * @param string $message
     * @param mixed  $context Optional additional context (will be JSON-encoded if array/object).
     */
    public static function debug( $message, $context = null ) {
        if ( ! self::is_debug_enabled() ) {
            return;
        }

        $log_message = '[Bol Affiliate Insights] ' . $message;

        if ( null !== $context ) {
            if ( is_array( $context ) || is_object( $context ) ) {
                $log_message .= ' | ' . wp_json_encode( $context );
            } else {
                $log_message .= ' | ' . (string) $context;
            }
        }

        error_log( $log_message );
    }
}

