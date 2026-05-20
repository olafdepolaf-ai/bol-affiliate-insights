<?php
namespace TuinenBalkon\BolAffiliateInsights\AffiliateLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * No-op adapter used when no affiliate link plugin is active.
 *
 * All methods return safe empty values so callers never need a null check
 * on the adapter itself — only on the returned data when needed.
 */
class NullAdapter implements AffiliateLinkAdapterInterface {

    public function is_available(): bool {
        return false;
    }

    public function get_plugin_name(): string {
        return 'None';
    }

    public function get_link_url( int $link_id ): ?string {
        return null;
    }

    public function get_link_redirect_url( int $link_id ): ?string {
        return null;
    }

    public function get_all_links(): array {
        return array();
    }

    public function find_link_by_url( string $url ): ?int {
        return null;
    }

    public function get_links_by_host( string $host ): array {
        return array();
    }

    public function get_admin_edit_url( int $link_id ): ?string {
        return null;
    }

    public function get_links_by_name(): array {
        return array();
    }

    public function build_bol_params_index(): array {
        return array( 'by_subid' => array(), 'by_name' => array() );
    }
}
