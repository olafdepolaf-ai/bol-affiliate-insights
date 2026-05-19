<?php

namespace TuinenBalkon\AffiliateLinkChecker;

class UpdateChecker {

	const GITHUB_REPO  = 'olafdepolaf-ai/bol-affiliate-insights';
	const TAG_PREFIX   = 'alc-v';
	const ASSET_NAME   = 'affiliate-link-checker.zip';
	const CACHE_KEY    = 'alc_github_update';
	const CACHE_TTL    = 21600; // 6 hours — cannot use HOUR_IN_SECONDS (define() constant) in class const

	private string $plugin_file;
	private string $plugin_slug;
	private string $current_version;

	public function __construct( string $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_slug     = plugin_basename( $plugin_file );
		$this->current_version = $this->get_plugin_version();

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
	}

	public function check_for_update( mixed $transient ): mixed {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( is_wp_error( $release ) || empty( $release ) ) {
			return $transient;
		}

		$update_obj = (object) [
			'slug'        => dirname( $this->plugin_slug ),
			'plugin'      => $this->plugin_slug,
			'new_version' => $release['version'],
			'url'         => $release['html_url'],
			'package'     => $release['download_url'],
		];

		if ( version_compare( $release['version'], $this->current_version, '>' ) ) {
			// Update beschikbaar — in response zodat WP "Update now" toont
			$transient->response[ $this->plugin_slug ] = $update_obj;
			// Verwijder eventueel stale no_update entry
			unset( $transient->no_update[ $this->plugin_slug ] );
		} else {
			// Up to date — in no_update zodat WP de auto-update toggle toont
			$transient->no_update[ $this->plugin_slug ] = $update_obj;
			unset( $transient->response[ $this->plugin_slug ] );
		}

		return $transient;
	}

	public function plugin_info( mixed $result, string $action, object $args ): mixed {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( $args->slug !== dirname( $this->plugin_slug ) ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( is_wp_error( $release ) || empty( $release ) ) {
			return $result;
		}

		return (object) [
			'name'          => 'Affiliate Link Checker',
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $release['version'],
			'download_link' => $release['download_url'],
			'sections'      => [
				'changelog' => $release['body'] ? $release['body'] : 'Zie GitHub voor wijzigingen.',
			],
		];
	}

	private function get_latest_release(): array|\WP_Error {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$api_url  = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases';
		$response = wp_remote_get( $api_url, [
			'timeout' => 10,
			'headers' => [ 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error( 'github_api', 'GitHub API niet bereikbaar.' );
		}

		$releases = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $releases ) ) {
			return new \WP_Error( 'github_api', 'Ongeldig antwoord van GitHub.' );
		}

		foreach ( $releases as $release ) {
			$tag = isset( $release['tag_name'] ) ? $release['tag_name'] : '';

			if ( strpos( $tag, self::TAG_PREFIX ) !== 0 ) {
				continue;
			}

			$version      = ltrim( substr( $tag, strlen( self::TAG_PREFIX ) ), 'v' );
			$download_url = $this->find_asset_url( isset( $release['assets'] ) ? $release['assets'] : [] );

			if ( empty( $download_url ) || empty( $version ) ) {
				continue;
			}

			$result = [
				'version'      => $version,
				'download_url' => $download_url,
				'html_url'     => isset( $release['html_url'] ) ? $release['html_url'] : '',
				'body'         => wp_kses_post( isset( $release['body'] ) ? $release['body'] : '' ),
			];

			set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

			return $result;
		}

		set_transient( self::CACHE_KEY, [], self::CACHE_TTL );

		return [];
	}

	private function find_asset_url( array $assets ) {
		foreach ( $assets as $asset ) {
			if ( ( isset( $asset['name'] ) ? $asset['name'] : '' ) === self::ASSET_NAME ) {
				return isset( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';
			}
		}
		return '';
	}

	private function get_plugin_version(): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( $this->plugin_file );
		return isset( $data['Version'] ) ? $data['Version'] : '0.0.0';
	}
}
