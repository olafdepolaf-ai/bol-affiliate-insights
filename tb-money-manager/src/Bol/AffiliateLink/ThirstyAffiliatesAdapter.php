<?php

namespace TuinenBalkon\TBMoneyManager\Bol\AffiliateLink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ThirstyAffiliatesAdapter implements AffiliateLinkAdapterInterface {

	public function is_available(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( 'thirstyaffiliates/thirstyaffiliates.php' ) ) {
			return true;
		}
		return post_type_exists( 'thirstylink' );
	}

	public function get_plugin_name(): string {
		return 'ThirstyAffiliates';
	}

	public function get_link_url( int $link_id ): ?string {
		$url = get_post_meta( $link_id, '_ta_destination_url', true );
		return $url ? (string) $url : null;
	}

	public function get_link_redirect_url( int $link_id ): ?string {
		$uncloak = get_post_meta( $link_id, '_ta_uncloak_link', true );
		if ( $uncloak == '1' ) {
			return $this->get_link_url( $link_id );
		}
		if ( post_type_exists( 'thirstylink' ) ) {
			$permalink = get_permalink( $link_id );
			if ( $permalink ) {
				return (string) $permalink;
			}
		}
		return $this->get_link_url( $link_id );
	}

	public function get_all_links(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT ID, post_title
			 FROM {$wpdb->posts}
			 WHERE post_type = 'thirstylink'
			   AND post_status = 'publish'
			 ORDER BY post_title ASC"
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$result = array();
		foreach ( $rows as $row ) {
			$id  = (int) $row->ID;
			$url = (string) get_post_meta( $id, '_ta_destination_url', true );
			$result[] = array(
				'id'           => $id,
				'name'         => $row->post_title,
				'url'          => $url,
				'redirect_url' => (string) ( $this->get_link_redirect_url( $id ) ?? $url ),
			);
		}
		return $result;
	}

	public function find_link_by_url( string $url ): ?int {
		foreach ( $this->get_all_links() as $link ) {
			if ( $link['url'] === $url ) {
				return $link['id'];
			}
		}
		return null;
	}

	public function get_links_by_host( string $host ): array {
		$host = strtolower( trim( $host ) );
		return array_values(
			array_filter(
				$this->get_all_links(),
				function ( $link ) use ( $host ) {
					$parsed = wp_parse_url( strtolower( $link['url'] ), PHP_URL_HOST );
					return $parsed !== false && $parsed !== null && strpos( $parsed, $host ) !== false;
				}
			)
		);
	}

	public function get_admin_edit_url( int $link_id ): ?string {
		return admin_url( 'post.php?post=' . $link_id . '&action=edit' );
	}

	public function get_links_by_name(): array {
		$map = array();
		foreach ( $this->get_all_links() as $link ) {
			$key = strtolower( trim( $link['name'] ) );
			if ( $key !== '' ) {
				$map[ $key ] = $link;
			}
		}
		return $map;
	}

	public function build_bol_params_index(): array {
		$by_subid = array();
		$by_name  = array();

		foreach ( $this->get_all_links() as $link ) {
			$url = $link['url'];
			if ( empty( $url ) || strpos( $url, 'partner.bol.com' ) === false ) {
				continue;
			}
			$parsed = wp_parse_url( $url );
			if ( empty( $parsed['query'] ) ) {
				continue;
			}
			parse_str( $parsed['query'], $params );

			$subid = isset( $params['subid'] ) ? trim( $params['subid'] ) : '';
			$name  = isset( $params['name'] )  ? trim( $params['name'] )  : '';

			if ( $subid !== '' ) {
				$by_subid[ strtolower( $subid ) ] = $link;
			}
			if ( $name !== '' ) {
				$by_name[ strtolower( $name ) ] = $link;
			}
		}

		return array(
			'by_subid' => $by_subid,
			'by_name'  => $by_name,
		);
	}
}
