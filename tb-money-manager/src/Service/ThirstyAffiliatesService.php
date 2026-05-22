<?php

namespace TuinenBalkon\TBMoneyManager\Service;

class ThirstyAffiliatesService {

	const CLICK_TABLE = 'ta_click_logs';

	/**
	 * Geeft klikken per TA-link terug voor het opgegeven jaar.
	 * Inclusief links met 0 kliks — gesorteerd hoog naar laag.
	 *
	 * @return array{items: array, total: int, table_missing: bool}
	 */
	public function get_clicks_by_year( int $year, int $page = 1, int $per_page = 50 ): array {
		global $wpdb;

		$table = $wpdb->prefix . self::CLICK_TABLE;

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table;

		if ( ! $table_exists ) {
			return [ 'items' => [], 'total' => 0, 'table_missing' => true ];
		}

		$offset = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.ID,
					p.post_title,
					pm.meta_value AS destination_url,
					COALESCE( cl.click_count, 0 ) AS click_count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm
					ON pm.post_id = p.ID AND pm.meta_key = '_ta_destination_url'
				LEFT JOIN (
					SELECT link_id, COUNT(*) AS click_count
					FROM {$table}
					WHERE YEAR(click_date) = %d
					GROUP BY link_id
				) cl ON cl.link_id = p.ID
				WHERE p.post_type = 'thirstylink'
				  AND p.post_status = 'publish'
				ORDER BY click_count DESC, p.post_title ASC
				LIMIT %d OFFSET %d",
				$year,
				$per_page,
				$offset
			)
		);
		// phpcs:enable

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'thirstylink' AND post_status = 'publish'"
		);

		return [ 'items' => $items ?: [], 'total' => $total, 'table_missing' => false ];
	}

	/**
	 * Geeft alle gepubliceerde ThirstyAffiliates links terug waarvan de destination URL
	 * de opgegeven zoekterm bevat (case-insensitive).
	 *
	 * @return object[] rows met ID, post_title, post_name, destination_url
	 */
	public function get_links_by_destination( string $search ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_name, pm.meta_value AS destination_url
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm
					ON pm.post_id = p.ID AND pm.meta_key = '_ta_destination_url'
				WHERE p.post_type = 'thirstylink'
				  AND p.post_status = 'publish'
				  AND pm.meta_value LIKE %s
				ORDER BY p.post_title ASC",
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		return $rows ?: [];
	}

	/**
	 * Geeft alle actieve ThirstyAffiliates slugs terug (post_name, lowercase).
	 *
	 * @return string[]
	 */
	public function get_active_slugs(): array {
		global $wpdb;

		$slugs = $wpdb->get_col(
			"SELECT post_name FROM {$wpdb->posts}
			WHERE post_type = 'thirstylink'
			AND post_status = 'publish'"
		);

		return array_map( 'strtolower', $slugs ?: [] );
	}

	/**
	 * Telt het aantal unieke /aanbeveling/-links per post.
	 *
	 * @param  int[] $post_ids
	 * @return array<int,int>  post_id => count
	 */
	public function count_ta_links_per_post( array $post_ids ): array {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_map( 'intval', $post_ids ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ({$placeholders})"
		);
		// phpcs:enable

		$counts = array_fill_keys( array_map( 'intval', $post_ids ), 0 );
		foreach ( $rows as $row ) {
			preg_match_all( '~/aanbeveling/([^"\'<>\s/?#]+)~i', $row->post_content, $matches );
			$counts[ (int) $row->ID ] = count( array_unique( $matches[1] ) );
		}

		return $counts;
	}

	/**
	 * Geeft het totaal aantal kliks in een jaar terug.
	 */
	public function get_total_clicks_for_year( int $year ): int {
		global $wpdb;

		$table = $wpdb->prefix . self::CLICK_TABLE;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE YEAR(click_date) = %d",
				$year
			)
		);
		// phpcs:enable
	}
}
