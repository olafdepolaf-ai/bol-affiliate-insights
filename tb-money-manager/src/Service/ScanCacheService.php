<?php

namespace TuinenBalkon\TBMoneyManager\Service;

class ScanCacheService {

	const TABLE = 'tbmm_scan_cache';

	public function get( string $scan_key ): ?array {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return null;
		}

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT scanned_at, result_json FROM {$table} WHERE scan_key = %s",
				$scan_key
			)
		);

		if ( ! $row ) {
			return null;
		}

		$results = json_decode( $row->result_json, true );

		return [
			'scanned_at' => $row->scanned_at,
			'results'    => is_array( $results ) ? $results : [],
		];
	}

	public function save( string $scan_key, array $results ): string {
		global $wpdb;

		$table      = $wpdb->prefix . self::TABLE;
		$scanned_at = current_time( 'mysql' );

		$wpdb->replace(
			$table,
			[
				'scan_key'    => $scan_key,
				'scanned_at'  => $scanned_at,
				'result_json' => wp_json_encode( $results ),
			],
			[ '%s', '%s', '%s' ]
		);

		return $scanned_at;
	}

	private function table_exists(): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
