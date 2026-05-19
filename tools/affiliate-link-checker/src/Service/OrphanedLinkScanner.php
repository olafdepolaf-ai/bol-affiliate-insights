<?php

namespace TuinenBalkon\AffiliateLinkChecker\Service;

class OrphanedLinkScanner {

	const REDIRECTION_TABLE    = 'redirection_404';
	const AANBEVELING_PREFIX   = '/aanbeveling/';

	/**
	 * Geldige periode-sleutels voor de Redirection 404-filter.
	 */
	const PERIODS = [
		'today'     => 'Vandaag',
		'yesterday' => 'Gisteren',
		'7days'     => 'Laatste 7 dagen',
		'30days'    => 'Laatste 30 dagen',
		'quarter'   => 'Laatste 3 maanden',
		'year'      => 'Dit jaar',
	];

	/**
	 * Haalt /aanbeveling/-hits op uit de Redirection plugin 404-logtabel.
	 *
	 * @param string $period Periode-sleutel uit self::PERIODS (default '7days').
	 * @return array{ table_missing: bool, items: array }
	 */
	public function get_redirection_404s( string $period = '7days' ): array {
		global $wpdb;

		$table = $wpdb->prefix . self::REDIRECTION_TABLE;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return [ 'table_missing' => true, 'items' => [] ];
		}

		if ( ! array_key_exists( $period, self::PERIODS ) ) {
			$period = '7days';
		}

		$period_sql = $this->period_to_sql( $period );
		$like       = $wpdb->esc_like( self::AANBEVELING_PREFIX ) . '%';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT url, COUNT(*) AS hits, MAX(created) AS last_hit
				FROM {$table}
				WHERE url LIKE %s
				AND {$period_sql}
				GROUP BY url
				ORDER BY hits DESC",
				$like
			)
		);
		// phpcs:enable

		return [ 'table_missing' => false, 'items' => $items ?: [] ];
	}

	/**
	 * Zet een periode-sleutel om naar een SQL WHERE-fragment op de `created`-kolom.
	 */
	private function period_to_sql( string $period ): string {
		switch ( $period ) {
			case 'today':
				return 'DATE(created) = CURDATE()';
			case 'yesterday':
				return "DATE(created) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
			case '30days':
				return "created >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
			case 'quarter':
				return "created >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
			case 'year':
				return "YEAR(created) = YEAR(CURDATE())";
			case '7days':
			default:
				return "created >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
		}
	}

	/**
	 * Geeft het totaal aantal gepubliceerde posts en pagina's terug.
	 */
	public function get_total_posts(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type IN ('post','page')
			AND post_status = 'publish'"
		);
	}

	/**
	 * Scant een batch gepubliceerde posts op orphaned /aanbeveling/-links.
	 * Doorzoekt zowel post_content als alle post_meta values die de prefix bevatten.
	 *
	 * @param int      $offset       Batch offset.
	 * @param int      $limit        Batch grootte.
	 * @param string[] $active_slugs Actieve ThirstyAffiliates post_names (lowercase).
	 * @return array[]
	 */
	public function scan_batch( int $offset, int $limit, array $active_slugs ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content
				FROM {$wpdb->posts}
				WHERE post_type IN ('post','page')
				AND post_status = 'publish'
				ORDER BY ID ASC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		// phpcs:enable

		$active_map = array_flip( $active_slugs );
		$orphans    = [];
		$meta_like  = '%' . $wpdb->esc_like( self::AANBEVELING_PREFIX ) . '%';

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;

			$found = $this->extract_slugs( $post->post_content ?? '' );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$meta_values = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta}
					WHERE post_id = %d AND meta_value LIKE %s",
					$post_id,
					$meta_like
				)
			);
			// phpcs:enable

			foreach ( $meta_values as $mv ) {
				$found = array_merge( $found, $this->extract_slugs( $mv ) );
			}

			foreach ( array_count_values( $found ) as $slug => $count ) {
				if ( ! isset( $active_map[ $slug ] ) ) {
					$orphans[] = [
						'post_id'     => $post_id,
						'post_title'  => $post->post_title,
						'edit_url'    => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
						'found_url'   => self::AANBEVELING_PREFIX . $slug,
						'occurrences' => $count,
					];
				}
			}
		}

		return $orphans;
	}

	/**
	 * Zoekt gepubliceerde posts/pagina's die de /aanbeveling/slug URL bevatten
	 * in post_content of post_meta.
	 *
	 * @return array[]
	 */
	public function find_articles_with_slug( string $slug ): array {
		global $wpdb;

		$url  = self::AANBEVELING_PREFIX . $slug;
		$like = '%' . $wpdb->esc_like( $url ) . '%';

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_type IN ('post','page')
				AND post_status = 'publish'
				AND post_content LIKE %s",
				$like
			)
		);

		$meta_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				WHERE meta_value LIKE %s",
				$like
			)
		);

		$result = [];
		$seen   = [];

		foreach ( $posts as $p ) {
			$id        = (int) $p->ID;
			$seen[$id] = true;
			$result[]  = [
				'post_id'    => $id,
				'post_title' => $p->post_title,
				'edit_url'   => admin_url( 'post.php?post=' . $id . '&action=edit' ),
			];
		}

		foreach ( $meta_ids as $meta_post_id ) {
			$id = (int) $meta_post_id;
			if ( isset( $seen[$id] ) ) {
				continue;
			}
			$title = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_title FROM {$wpdb->posts}
					WHERE ID = %d AND post_status = 'publish'
					AND post_type IN ('post','page')",
					$id
				)
			);
			if ( $title ) {
				$result[] = [
					'post_id'    => $id,
					'post_title' => $title,
					'edit_url'   => admin_url( 'post.php?post=' . $id . '&action=edit' ),
				];
			}
		}

		return $result;
	}

	/**
	 * Extraheert /aanbeveling/-slugs uit een stuk tekst/HTML.
	 *
	 * @return string[]
	 */
	private function extract_slugs( string $content ): array {
		$slugs = [];
		preg_match_all( '~/aanbeveling/([^"\'<>\s/?#]+)~', $content, $matches );
		foreach ( $matches[1] as $slug ) {
			$slug = strtolower( rtrim( $slug, '/' ) );
			if ( $slug !== '' ) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}
}
