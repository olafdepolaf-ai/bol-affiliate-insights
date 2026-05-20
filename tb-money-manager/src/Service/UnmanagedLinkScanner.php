<?php

namespace TuinenBalkon\TBMoneyManager\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UnmanagedLinkScanner {

	const TABLE = 'tbmm_unmanaged_links';

	// Patroontypen die gescand worden
	const TYPES = array(
		'bol_tracked'     => 'Bol.com (partner-link)',
		'tradetracker'    => 'TradeTracker',
		'bol_direct'      => 'Bol.com (directe link)',
		'amazon_tracked'  => 'Amazon (met affiliate tag)',
		'amazon_direct'   => 'Amazon (geen affiliate tag!) ⚠',
	);

	public function scan_init( array $active_types ): int {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$wpdb->query( "TRUNCATE TABLE {$table}" );

		$ta_index = $this->build_ta_index();
		set_transient( 'tbmm_unmanaged_ta_index',    $ta_index,    600 );
		set_transient( 'tbmm_unmanaged_active_types', $active_types, 600 );

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type IN ('post', 'page')"
		);
	}

	public function scan_batch( int $offset, int $limit, array $active_types ): int {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$ta_index = get_transient( 'tbmm_unmanaged_ta_index' );
		if ( ! is_array( $ta_index ) ) {
			$ta_index = $this->build_ta_index();
		}

		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_content
			 FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type IN ('post', 'page')
			 ORDER BY ID ASC
			 LIMIT %d OFFSET %d",
			$limit,
			$offset
		) );

		$found       = 0;
		$now         = current_time( 'mysql' );
		$block_cache = array(); // wp_block post_content keyed by ref ID, shared across batch

		foreach ( $posts as $post ) {
			$content = $this->resolve_block_refs( $post->post_content, $block_cache );
			$links   = $this->extract_links( $content );
			foreach ( $links as $link ) {
				$type = $this->classify_url( $link['url'] );
				if ( ! $type || ! in_array( $type, $active_types, true ) ) {
					continue;
				}
				$ta_match = $this->find_ta_match( $link['url'], $ta_index );

				$wpdb->insert(
					$table,
					array(
						'post_id'         => (int) $post->ID,
						'post_title'      => substr( $post->post_title, 0, 500 ),
						'url'             => $link['url'],
						'link_type'       => $type,
						'anchor_text'     => substr( $link['anchor'], 0, 500 ),
						'ta_link_id'      => $ta_match ? $ta_match['id'] : null,
						'ta_link_name'    => $ta_match ? $ta_match['name'] : null,
						'ta_redirect_url' => $ta_match ? $ta_match['redirect_url'] : null,
						'scanned_at'      => $now,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				);
				$found++;
			}
		}

		return $found;
	}

	public function get_results( array $type_filter = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		if ( ! empty( $type_filter ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $type_filter ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE link_type IN ({$placeholders}) ORDER BY post_title ASC, link_type ASC, id ASC",
				...$type_filter
			), ARRAY_A );
		} else {
			$rows = $wpdb->get_results(
				"SELECT * FROM {$table} ORDER BY post_title ASC, link_type ASC, id ASC",
				ARRAY_A
			);
		}

		return $rows ?: array();
	}

	public function get_scan_meta(): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$row = $wpdb->get_row(
			"SELECT COUNT(*) as total, MAX(scanned_at) as scanned_at FROM {$table}",
			ARRAY_A
		);

		if ( ! $row || $row['scanned_at'] === null ) {
			return array();
		}

		return array(
			'scanned_at' => $row['scanned_at'],
			'total'      => (int) $row['total'],
		);
	}

	public function replace_link( int $row_id ): array|\WP_Error {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$row_id
		), ARRAY_A );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Rij niet gevonden in scan-tabel.' );
		}
		if ( empty( $row['ta_redirect_url'] ) ) {
			return new \WP_Error( 'no_ta_match', 'Geen ThirstyAffiliates match voor dit record.' );
		}

		$post = get_post( (int) $row['post_id'] );
		if ( ! $post ) {
			return new \WP_Error( 'no_post', 'Artikel niet gevonden (ID ' . $row['post_id'] . ').' );
		}

		$old_url          = $row['url'];
		$old_url_encoded  = htmlspecialchars( $old_url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$new_url          = $row['ta_redirect_url'];
		$content          = $post->post_content;

		// Probeer zowel de rauwe URL als de HTML-entity-encoded variant,
		// want WordPress slaat href-waarden op als &amp; in de post_content.
		$search  = array(
			'href="' . $old_url_encoded . '"',
			"href='" . $old_url_encoded . "'",
			'href="' . $old_url . '"',
			"href='" . $old_url . "'",
		);
		$replace = array(
			'href="' . $new_url . '"',
			"href='" . $new_url . "'",
			'href="' . $new_url . '"',
			"href='" . $new_url . "'",
		);

		$count       = 0;
		$new_content = str_replace( $search, $replace, $content, $count );

		if ( $count === 0 ) {
			return new \WP_Error( 'not_replaced', 'URL niet gevonden in post-inhoud — mogelijk al vervangen of URL is anders gecodeerd.' );
		}

		$updated = wp_update_post( array(
			'ID'           => $post->ID,
			'post_content' => $new_content,
		), true );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );

		return array(
			'replaced_count' => $count,
			'post_id'        => $post->ID,
			'old_url'        => $old_url,
			'new_url'        => $new_url,
		);
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function extract_links( string $content ): array {
		if ( empty( $content ) ) {
			return array();
		}
		preg_match_all(
			'~<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',
			$content,
			$matches,
			PREG_SET_ORDER
		);
		$links = array();
		foreach ( $matches as $m ) {
			// WordPress slaat href-waarden op met HTML-entities (&amp; ipv &).
			// Decodeer naar echte URL zodat matching met TA destination URLs klopt.
			$url = html_entity_decode( trim( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( $url && ! str_starts_with( $url, '#' ) && ! str_starts_with( $url, 'mailto:' ) ) {
				$links[] = array(
					'url'    => $url,
					'anchor' => strip_tags( trim( $m[2] ) ),
				);
			}
		}
		return $links;
	}

	private function classify_url( string $url ): ?string {
		if ( preg_match( '~^https?://partner\.bol\.com/~i', $url ) ) {
			return 'bol_tracked';
		}
		if ( preg_match( '~tradetracker\.net~i', $url ) ) {
			return 'tradetracker';
		}
		if ( preg_match( '~^https?://(?:www\.)?bol\.com/~i', $url ) ) {
			return 'bol_direct';
		}
		if ( preg_match( '~^https?://(?:www\.)?amazon\.(nl|de|com|co\.uk|fr|it|es|ca)/|^https?://amzn\.to/~i', $url ) ) {
			// tag=tuinennl-21 (NL) of tag=tuienbal-21 (DE) → getrackt via Amazon Associates
			// Geen tag → helemaal geen commissie
			parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $params );
			return isset( $params['tag'] ) && $params['tag'] !== '' ? 'amazon_tracked' : 'amazon_direct';
		}
		return null;
	}

	private function build_ta_index(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts}
			 WHERE post_type = 'thirstylink' AND post_status = 'publish'"
		);

		$index = array();
		foreach ( $rows as $row ) {
			$dest_url = (string) get_post_meta( (int) $row->ID, '_ta_destination_url', true );
			if ( ! $dest_url ) {
				continue;
			}
			$uncloak      = get_post_meta( (int) $row->ID, '_ta_uncloak_link', true );
			$redirect_url = ( $uncloak == '1' )
				? $dest_url
				: ( (string) ( get_permalink( (int) $row->ID ) ?: $dest_url ) );

			$entry = array(
				'id'           => (int) $row->ID,
				'name'         => $row->post_title,
				'redirect_url' => $redirect_url,
			);
			$index[ trim( $dest_url ) ]                = $entry;
			$index[ rtrim( trim( $dest_url ), '/' ) ]  = $entry;
			$index[ rtrim( trim( $dest_url ), '/' ) . '/' ] = $entry;
		}

		return $index;
	}

	private function resolve_block_refs( string $content, array &$block_cache ): string {
		if ( ! preg_match_all( '/<!--\s*wp:block\s+{"ref":(\d+)}/', $content, $matches ) ) {
			return $content;
		}
		foreach ( array_unique( $matches[1] ) as $ref_id ) {
			$ref_id = (int) $ref_id;
			if ( ! isset( $block_cache[ $ref_id ] ) ) {
				$block_post               = get_post( $ref_id );
				$block_cache[ $ref_id ]   = ( $block_post && $block_post->post_type === 'wp_block' )
					? $block_post->post_content
					: '';
			}
			if ( $block_cache[ $ref_id ] ) {
				$content .= "\n" . $block_cache[ $ref_id ];
			}
		}
		return $content;
	}

	private function find_ta_match( string $url, array $ta_index ): ?array {
		$url = trim( $url );
		if ( isset( $ta_index[ $url ] ) ) {
			return $ta_index[ $url ];
		}
		$url_no_slash = rtrim( $url, '/' );
		if ( isset( $ta_index[ $url_no_slash ] ) ) {
			return $ta_index[ $url_no_slash ];
		}
		if ( isset( $ta_index[ $url_no_slash . '/' ] ) ) {
			return $ta_index[ $url_no_slash . '/' ];
		}
		return null;
	}
}
