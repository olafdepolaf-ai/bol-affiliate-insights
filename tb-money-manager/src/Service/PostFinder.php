<?php

namespace TuinenBalkon\TBMoneyManager\Service;

class PostFinder {

	public function find_posts_for_link( int $link_id ): array {
		global $wpdb;

		$cloaked_url = get_permalink( $link_id );

		if ( ! $cloaked_url ) {
			return [];
		}

		// Strip protocol so we match http and https variants.
		$url_path = preg_replace( '#^https?://#', '', $cloaked_url );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title
				 FROM {$wpdb->posts}
				 WHERE (post_content LIKE %s OR post_content LIKE %s)
				   AND post_status = 'publish'
				   AND post_type IN ('post', 'page')",
				'%' . $wpdb->esc_like( $cloaked_url ) . '%',
				'%' . $wpdb->esc_like( $url_path ) . '%'
			)
		);

		if ( empty( $rows ) ) {
			return [];
		}

		$posts = [];
		foreach ( $rows as $row ) {
			$posts[] = [
				'id'       => (int) $row->ID,
				'title'    => $row->post_title,
				'edit_url' => admin_url( 'post.php?post=' . $row->ID . '&action=edit' ),
			];
		}

		return $posts;
	}
}
