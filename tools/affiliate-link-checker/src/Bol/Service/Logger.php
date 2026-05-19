<?php

namespace TuinenBalkon\AffiliateLinkChecker\Bol\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	public static function is_debug_enabled(): bool {
		return (bool) get_option( 'bol_affiliate_insights_debug_enabled', false );
	}

	public static function debug( string $message, $context = null ): void {
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
