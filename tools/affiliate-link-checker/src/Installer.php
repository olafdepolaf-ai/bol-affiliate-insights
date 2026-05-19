<?php

namespace TuinenBalkon\AffiliateLinkChecker;

use TuinenBalkon\AffiliateLinkChecker\Service\ScanCacheService;

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
	}

	public static function uninstall(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . ScanCacheService::TABLE );
	}
}
