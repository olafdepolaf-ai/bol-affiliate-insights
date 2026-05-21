<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Service\LinkScanner;
use TuinenBalkon\TBMoneyManager\Service\OrphanedLinkScanner;
use TuinenBalkon\TBMoneyManager\Service\ScanCacheService;
use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;
use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;
use TuinenBalkon\TBMoneyManager\Service\UnmanagedLinkScanner;

class AjaxHandlerService {

	private LinkScanner              $link_scanner;
	private OrphanedLinkScanner      $orphaned_scanner;
	private ScanCacheService         $scan_cache;
	private ThirstyAffiliatesService $ta_service;
	private TradeTrackerService      $tt_service;
	private UnmanagedLinkScanner     $unmanaged_scanner;

	public function __construct(
		LinkScanner $link_scanner,
		OrphanedLinkScanner $orphaned_scanner,
		ScanCacheService $scan_cache,
		ThirstyAffiliatesService $ta_service,
		TradeTrackerService $tt_service,
		UnmanagedLinkScanner $unmanaged_scanner
	) {
		$this->link_scanner      = $link_scanner;
		$this->orphaned_scanner  = $orphaned_scanner;
		$this->scan_cache        = $scan_cache;
		$this->ta_service        = $ta_service;
		$this->tt_service        = $tt_service;
		$this->unmanaged_scanner = $unmanaged_scanner;

		add_action( 'wp_ajax_tbmm_check_link',               [ $this, 'handle_check_link' ] );
		add_action( 'wp_ajax_tbmm_orphan_init',              [ $this, 'handle_orphan_init' ] );
		add_action( 'wp_ajax_tbmm_orphan_batch',             [ $this, 'handle_orphan_batch' ] );
		add_action( 'wp_ajax_tbmm_orphan_save',              [ $this, 'handle_orphan_save' ] );
		add_action( 'wp_ajax_tbmm_orphan_find_articles',     [ $this, 'handle_orphan_find_articles' ] );
		add_action( 'wp_ajax_tbmm_feed_search',              [ $this, 'handle_feed_search' ] );
		add_action( 'wp_ajax_tbmm_unmanaged_init',           [ $this, 'handle_unmanaged_init' ] );
		add_action( 'wp_ajax_tbmm_unmanaged_batch',          [ $this, 'handle_unmanaged_batch' ] );
		add_action( 'wp_ajax_tbmm_replace_unmanaged_link',   [ $this, 'handle_replace_unmanaged_link' ] );
	}

	/**
	 * Verifieert nonce en beheerdersrechten. Beëindigt het verzoek bij falen.
	 */
	private function authorize( string $nonce_action ): void {
		check_ajax_referer( $nonce_action, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Geen toegang.' ], 403 );
		}
	}

	public function handle_check_link(): void {
		$this->authorize( 'tbmm_run_scan_nonce' );

		$link_id  = isset( $_POST['link_id'] ) ? (int) $_POST['link_id'] : 0;
		$link_url = isset( $_POST['link_url'] ) ? esc_url_raw( wp_unslash( $_POST['link_url'] ) ) : '';

		if ( ! $link_id || ! $link_url || ! wp_http_validate_url( $link_url ) ) {
			wp_send_json_error( [ 'message' => 'Ongeldige parameters.' ] );
		}

		$result = $this->link_scanner->check_single( $link_id, $link_url );

		wp_send_json_success( $result );
	}

	public function handle_orphan_init(): void {
		$this->authorize( 'tbmm_orphan_nonce' );

		wp_send_json_success( [
			'total_posts' => $this->orphaned_scanner->get_total_posts(),
		] );
	}

	public function handle_orphan_batch(): void {
		$this->authorize( 'tbmm_orphan_nonce' );

		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$limit  = isset( $_POST['limit'] )  ? (int) $_POST['limit']  : 15;
		$limit  = min( max( $limit, 1 ), 50 );

		$active_slugs = $this->ta_service->get_active_slugs();
		$orphans      = $this->orphaned_scanner->scan_batch( $offset, $limit, $active_slugs );

		wp_send_json_success( [ 'orphans' => $orphans ] );
	}

	public function handle_orphan_save(): void {
		$this->authorize( 'tbmm_orphan_nonce' );

		$raw     = isset( $_POST['results'] ) ? wp_unslash( $_POST['results'] ) : '[]';
		$results = json_decode( $raw, true );

		if ( ! is_array( $results ) ) {
			$results = [];
		}

		$scanned_at = $this->scan_cache->save( 'orphaned_aanbeveling', $results );

		wp_send_json_success( [ 'scanned_at' => $scanned_at ] );
	}

	public function handle_orphan_find_articles(): void {
		$this->authorize( 'tbmm_orphan_nonce' );

		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( [ 'message' => 'Ongeldige slug.' ] );
		}

		wp_send_json_success( [
			'articles' => $this->orphaned_scanner->find_articles_with_slug( $slug ),
		] );
	}

	public function handle_feed_search(): void {
		$this->authorize( 'tbmm_tt_feed_nonce' );

		$feed_id     = isset( $_POST['feed_id'] )     ? (int) $_POST['feed_id']                                    : 0;
		$search      = isset( $_POST['search'] )      ? sanitize_text_field( wp_unslash( $_POST['search'] ) )      : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign_id'] ) ) : '';
		$per_page    = isset( $_POST['per_page'] )    ? (int) $_POST['per_page']                                    : 25;
		$page        = isset( $_POST['page'] )        ? (int) $_POST['page']                                        : 1;

		$per_page = in_array( $per_page, [ 10, 25, 50, 100, 500 ], true ) ? $per_page : 25;
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		$site_id = $this->tt_service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			wp_send_json_error( [ 'message' => $site_id->get_error_message() ] );
		}

		// With a keyword: fetch 200 items and re-sort client-side for better relevance.
		// Without a keyword: use exact page size — rely on server-side offset pagination.
		$fetch_limit = $search !== '' ? 200 : $per_page;

		if ( $feed_id ) {
			// Enkelvoudige feed: bij zoekterm altijd vanaf offset 0 fetchen,
			// paginering doen via client-side slice op gefilterde resultaten.
			$fetch_offset = $search !== '' ? 0 : $offset;

			$raw = $this->tt_service->get_feed_products( $site_id, $feed_id, $search, $fetch_limit, $fetch_offset );
			if ( is_wp_error( $raw ) ) {
				wp_send_json_error( [ 'message' => $raw->get_error_message() ] );
			}

			if ( $search !== '' ) {
				$raw      = $this->filter_raw_by_search( $raw, $search );
				$products = $this->sort_by_relevance( $this->normalize_products( $raw ), $search );
				$total    = count( $products );
				$products = array_slice( $products, $offset, $per_page );
				$has_more = ( $offset + $per_page ) < $total;
			} else {
				$products = $this->normalize_products( $raw );
				$total    = null;
				$has_more = count( $products ) === $per_page;
			}

			wp_send_json_success( [
				'products'    => $products,
				'page'        => $page,
				'per_page'    => $per_page,
				'has_more'    => $has_more,
				'all_feeds'   => false,
				'total_found' => $total,
			] );
		}

		// Cross-feed: require at least a campaign filter or a search keyword.
		// Browsing all feeds without any filter would return an unmanageably large set.
		if ( $search === '' && $campaign_id === '' ) {
			wp_send_json_error( [ 'message' => 'Selecteer een campagne of voer een zoekwoord in.' ] );
		}

		$feeds = $this->tt_service->get_feeds( $site_id, 'accepted' );
		if ( is_wp_error( $feeds ) ) {
			wp_send_json_error( [ 'message' => $feeds->get_error_message() ] );
		}

		$all_products = [];
		foreach ( $feeds as $feed ) {
			$f   = is_object( $feed ) ? $feed : (object) $feed;
			$fid = (int) ( $f->ID ?? 0 );
			if ( ! $fid ) {
				continue;
			}

			if ( $campaign_id !== '' ) {
				$camp    = is_object( $f->campaign ?? null ) ? $f->campaign : null;
				$camp_id = $camp ? (string) ( $camp->ID ?? '' ) : '';
				if ( $camp_id !== $campaign_id ) {
					continue;
				}
			}

			if ( $search !== '' ) {
				// With keyword: fetch large batch from offset 0, filter + sort for relevance.
				$raw = $this->tt_service->get_feed_products( $site_id, $fid, $search, $fetch_limit, 0 );
				if ( is_wp_error( $raw ) || empty( $raw ) ) {
					continue;
				}
				$raw       = $this->filter_raw_by_search( $raw, $search );
				$feed_name = (string) ( $f->name ?? '' );
				foreach ( $this->normalize_products( $raw ) as $product ) {
					$product['feed_name'] = $feed_name;
					$all_products[]       = $product;
				}
			} else {
				// No keyword, campaign filter only: paginate via server-side offset per feed.
				$raw = $this->tt_service->get_feed_products( $site_id, $fid, '', $per_page, $offset );
				if ( is_wp_error( $raw ) || empty( $raw ) ) {
					continue;
				}
				$feed_name = (string) ( $f->name ?? '' );
				foreach ( $this->normalize_products( $raw ) as $product ) {
					$product['feed_name'] = $feed_name;
					$all_products[]       = $product;
				}
			}
		}

		if ( $search !== '' ) {
			$all_products = $this->sort_by_relevance( $all_products, $search );
			$products     = array_slice( $all_products, 0, $per_page );
			$has_more     = false;
			$total_found  = count( $all_products );
		} else {
			// No-keyword browse: products are already at the correct offset, show them as-is.
			$products    = array_slice( $all_products, 0, $per_page );
			$has_more    = count( $all_products ) >= $per_page;
			$total_found = null;
		}

		wp_send_json_success( [
			'products'    => $products,
			'page'        => $page,
			'per_page'    => $per_page,
			'has_more'    => $has_more,
			'all_feeds'   => true,
			'total_found' => $total_found,
		] );
	}

	/**
	 * Filtert ruwe SOAP-producten op zoekterm vóór normalisatie,
	 * zodat de volledige beschrijvingstekst beschikbaar is voor matching.
	 * Houdt alleen producten waarbij naam of beschrijving de term bevat (case-insensitive).
	 *
	 * @param  mixed[] $raw
	 * @return mixed[]
	 */
	private function filter_raw_by_search( array $raw, string $search ): array {
		$term = mb_strtolower( $search );
		return array_values( array_filter( $raw, function( $item ) use ( $term ): bool {
			$p    = is_object( $item ) ? $item : (object) $item;
			$name = mb_strtolower( (string) ( $p->name ?? $p->Name ?? '' ) );
			$desc = mb_strtolower( (string) ( $p->description ?? $p->Description ?? '' ) );
			return str_contains( $name, $term ) || str_contains( $desc, $term );
		} ) );
	}

	/**
	 * Sorteert genormaliseerde producten op relevantie voor het zoekwoord.
	 * Rangorde: exacte naam = term > naam begint met term > term als heel woord in naam > term ergens in naam.
	 *
	 * @param  array[] $products
	 * @return array[]
	 */
	private function sort_by_relevance( array $products, string $search ): array {
		if ( $search === '' ) {
			return $products;
		}
		$term = mb_strtolower( $search );

		usort( $products, function( array $a, array $b ) use ( $term ): int {
			return $this->relevance_score( $b, $term ) - $this->relevance_score( $a, $term );
		} );

		return $products;
	}

	private function relevance_score( array $product, string $term ): int {
		$name = mb_strtolower( $product['name'] );
		if ( $name === $term )                           return 4;
		if ( str_starts_with( $name, $term . ' ' ) )    return 3;
		if ( str_contains( $name, ' ' . $term . ' ' ) ) return 2;
		if ( str_contains( $name, $term ) )              return 1;
		return 0;
	}

	/**
	 * Zet ruwe SOAP-producten om naar genormaliseerde arrays voor de frontend.
	 *
	 * @param  mixed[] $raw
	 * @return array[]
	 */
	public function handle_unmanaged_init(): void {
		$this->authorize( 'tbmm_unmanaged_nonce' );

		$all_types    = array_keys( UnmanagedLinkScanner::TYPES );
		$raw_types    = isset( $_POST['types'] ) && is_array( $_POST['types'] ) ? $_POST['types'] : $all_types;
		$active_types = array_values( array_intersect( array_map( 'sanitize_key', $raw_types ), $all_types ) );

		if ( empty( $active_types ) ) {
			wp_send_json_error( [ 'message' => 'Geen geldige patronen geselecteerd.' ] );
		}

		$total_posts = $this->unmanaged_scanner->scan_init( $active_types );

		wp_send_json_success( [
			'total_posts'  => $total_posts,
			'active_types' => $active_types,
		] );
	}

	public function handle_unmanaged_batch(): void {
		$this->authorize( 'tbmm_unmanaged_nonce' );

		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$limit  = isset( $_POST['limit'] )  ? (int) $_POST['limit']  : 15;
		$limit  = min( max( $limit, 1 ), 50 );

		$all_types    = array_keys( UnmanagedLinkScanner::TYPES );
		$raw_types    = isset( $_POST['types'] ) && is_array( $_POST['types'] ) ? $_POST['types'] : $all_types;
		$active_types = array_values( array_intersect( array_map( 'sanitize_key', $raw_types ), $all_types ) );

		$found = $this->unmanaged_scanner->scan_batch( $offset, $limit, $active_types );

		wp_send_json_success( [ 'found' => $found ] );
	}

	public function handle_replace_unmanaged_link(): void {
		$this->authorize( 'tbmm_unmanaged_nonce' );

		$row_id = isset( $_POST['row_id'] ) ? (int) $_POST['row_id'] : 0;
		if ( $row_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Ongeldig row-ID.' ] );
		}

		$result = $this->unmanaged_scanner->replace_link( $row_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	private function normalize_products( array $raw ): array {
		$products = [];
		foreach ( $raw as $item ) {
			$p     = is_object( $item ) ? $item : (object) $item;
			$image = (string) (
				$p->imageURL ?? $p->imageUrl ?? $p->image_url ?? $p->image ?? $p->imageSmallURL ?? $p->imageSmallUrl ?? ''
			);
			$url       = (string) ( $p->URL ?? $p->url ?? $p->productURL ?? '' );
			$price_raw = $p->price ?? $p->Price ?? null;
			$price     = $price_raw !== null ? number_format( (float) $price_raw, 2, ',', '.' ) : '';
			$desc      = (string) ( $p->description ?? $p->Description ?? '' );
			if ( strlen( $desc ) > 200 ) {
				$desc = substr( $desc, 0, 197 ) . '...';
			}
			$products[] = [
				'name'      => (string) ( $p->name ?? $p->Name ?? '' ),
				'desc'      => $desc,
				'price'     => $price,
				'image'     => $image,
				'url'       => $url,
				'category'  => (string) ( $p->categoryName ?? $p->category ?? '' ),
				'ean'       => (string) ( $p->EAN ?? $p->ean ?? '' ),
				'feed_name' => '',
			];
		}
		return $products;
	}
}
