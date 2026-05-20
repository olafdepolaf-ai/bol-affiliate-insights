<?php

namespace TuinenBalkon\TBMoneyManager;

use TuinenBalkon\TBMoneyManager\Service\ScanCacheService;
use TuinenBalkon\TBMoneyManager\Service\UnmanagedLinkScanner;

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

		$unmanaged_table = $wpdb->prefix . UnmanagedLinkScanner::TABLE;
		$sql2 = "CREATE TABLE {$unmanaged_table} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id         BIGINT UNSIGNED NOT NULL,
			post_title      VARCHAR(500)    NOT NULL DEFAULT '',
			url             TEXT            NOT NULL,
			link_type       VARCHAR(30)     NOT NULL,
			anchor_text     VARCHAR(1000)   NOT NULL DEFAULT '',
			ta_link_id      BIGINT UNSIGNED NULL,
			ta_link_name    VARCHAR(500)    NULL,
			ta_redirect_url TEXT            NULL,
			scanned_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY link_type (link_type)
		) {$charset};";
		dbDelta( $sql2 );
	}

	public static function uninstall(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . ScanCacheService::TABLE );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . UnmanagedLinkScanner::TABLE );
		delete_transient( 'tbmm_unmanaged_ta_index' );
		delete_transient( 'tbmm_unmanaged_active_types' );
	}
}
