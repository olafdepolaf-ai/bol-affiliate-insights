<?php

namespace TuinenBalkon\TBMoneyManager;

use TuinenBalkon\TBMoneyManager\Service\ScanCacheService;

class Installer {

	public static function activate(): void {
		global $wpdb;

		$table   = $wpdb->prefix . ScanCacheService::TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_key    VARCHAR(100) NOT NULL,
			scanned_at  DATETIME     NOT NULL,
			result_json LONGTEXT     NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY scan_key (scan_key)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// TODO(cleanup): remove after v0.3.x — migrates legacy alc_ data from Affiliate Link Checker era
		self::migrate_from_alc();
	}

	/**
	 * Migrates wp_options and transients from the old alc_ prefix (Affiliate Link Checker)
	 * to the current tbmm_ prefix. Drops the old scan cache table.
	 * TODO(cleanup): remove after v0.3.x
	 */
	private static function migrate_from_alc(): void {
		global $wpdb;

		// Migrate TradeTracker credentials (user data — copy to new key, delete old)
		foreach ( [ 'tt_customer_id', 'tt_access_key' ] as $key ) {
			$old_value = get_option( "alc_{$key}" );
			if ( false !== $old_value ) {
				update_option( "tbmm_{$key}", $old_value );
				delete_option( "alc_{$key}" );
			}
		}

		// Drop legacy scan cache table (just cached results, safe to discard)
		$old_table = $wpdb->prefix . 'alc_scan_cache';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$old_table}" );

		// Delete legacy transients (auto-regenerated on next request)
		delete_transient( 'alc_github_update' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_alc\_%' OR option_name LIKE '_transient_timeout_alc\_%'"
		);
	}

	public static function uninstall(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . ScanCacheService::TABLE );
	}
}
