<?php
namespace TuinenBalkon\BolAffiliateInsights\AffiliateLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contract for affiliate link plugin adapters.
 *
 * Implement this interface for any affiliate link plugin to make it work with
 * Bol Affiliate Insights without touching the rest of the plugin.
 */
interface AffiliateLinkAdapterInterface {

    /**
     * Returns true when the underlying affiliate link plugin is active and usable.
     */
    public function is_available(): bool;

    /**
     * Returns the name of the affiliate link plugin this adapter wraps.
     */
    public function get_plugin_name(): string;

    /**
     * Returns the destination URL for a given link ID, or null if not found.
     *
     * @param int $link_id
     * @return string|null
     */
    public function get_link_url( int $link_id ): ?string;

    /**
     * Returns the cloaked/redirect URL for a given link ID, or null if not found.
     * Falls back to the destination URL when cloaking is disabled.
     *
     * @param int $link_id
     * @return string|null
     */
    public function get_link_redirect_url( int $link_id ): ?string;

    /**
     * Returns all affiliate links as a flat array.
     * Each entry: [ 'id' => int, 'name' => string, 'url' => string, 'redirect_url' => string ]
     *
     * @return array
     */
    public function get_all_links(): array;

    /**
     * Finds a link whose destination URL matches the given URL.
     * Returns the link ID on match, or null when nothing is found.
     *
     * @param string $url
     * @return int|null
     */
    public function find_link_by_url( string $url ): ?int;

    /**
     * Finds all links whose destination URL contains the given host (e.g. "bol.com").
     * Returns an array in the same format as get_all_links().
     *
     * @param string $host
     * @return array
     */
    public function get_links_by_host( string $host ): array;

    /**
     * Returns a WordPress admin URL to edit the given link, or null when not supported.
     *
     * @param int $link_id
     * @return string|null
     */
    public function get_admin_edit_url( int $link_id ): ?string;

    /**
     * Returns a map of lowercased link name => link data for fast lookup.
     * Each value: [ 'id' => int, 'name' => string, 'url' => string, 'redirect_url' => string ]
     *
     * @return array
     */
    public function get_links_by_name(): array;

    /**
     * Parses all bol.com affiliate links and builds a lookup index on the
     * 'name' and 'subid' query parameters found in the destination URL.
     *
     * Only processes links whose destination URL contains 'partner.bol.com'.
     *
     * Returns:
     * [
     *   'by_subid' => [ 'lowercased_subid' => link_data, ... ],
     *   'by_name'  => [ 'lowercased_name'  => link_data, ... ],
     * ]
     *
     * @return array
     */
    public function build_bol_params_index(): array;
}
