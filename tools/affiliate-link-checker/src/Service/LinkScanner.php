<?php

namespace TuinenBalkon\AffiliateLinkChecker\Service;

class LinkScanner {

	private PostFinder $post_finder;

	public function __construct( PostFinder $post_finder ) {
		$this->post_finder = $post_finder;
	}

	public function get_stats(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value AS destination_url
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm
			     ON pm.post_id = p.ID AND pm.meta_key = '_ta_destination_url'
			 WHERE p.post_type = 'thirstylink'
			   AND p.post_status = 'publish'
			 ORDER BY p.post_title ASC"
		);

		$total      = 0;
		$bol_count  = 0;
		$scan_links = [];
		$domains    = [];

		foreach ( $rows as $row ) {
			$url = (string) ( $row->destination_url ?? '' );

			if ( $url === '' ) {
				continue;
			}

			$id = (int) $row->ID;
			$total++;

			if ( strpos( $url, 'partner.bol.com' ) !== false ) {
				$bol_count++;
				$domains['partner.bol.com (overgeslagen)'] = ( $domains['partner.bol.com (overgeslagen)'] ?? 0 ) + 1;
			} else {
				$host             = parse_url( $url, PHP_URL_HOST ) ?: 'onbekend';
				$domains[ $host ] = ( $domains[ $host ] ?? 0 ) + 1;
				$scan_links[]     = [
					'id'   => $id,
					'name' => $row->post_title,
					'url'  => $url,
				];
			}
		}

		arsort( $domains );

		return [
			'total'      => $total,
			'bol_count'  => $bol_count,
			'scan_count' => count( $scan_links ),
			'domains'    => $domains,
			'scan_links' => $scan_links,
		];
	}

	public function check_link( string $url ): int {
		// Reject malformed URLs and guard against SSRF via wp_http_validate_url().
		if ( ! wp_http_validate_url( $url ) ) {
			return 0;
		}

		$response = wp_remote_get( $url, [
			'timeout'     => 10,
			'redirection' => 5,
		] );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		return (int) wp_remote_retrieve_response_code( $response );
	}

	public function check_single( int $id, string $url ): array {
		$status    = $this->check_link( $url );
		$is_broken = ( $status === 404 || $status >= 500 );

		$result = [
			'status'    => $status,
			'is_broken' => $is_broken,
		];

		if ( $is_broken ) {
			$result['posts']    = $this->post_finder->find_posts_for_link( $id );
			$result['edit_url'] = admin_url( 'post.php?post=' . $id . '&action=edit' );
		}

		return $result;
	}
}
