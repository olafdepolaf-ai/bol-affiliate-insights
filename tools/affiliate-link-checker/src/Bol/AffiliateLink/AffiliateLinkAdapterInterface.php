<?php

namespace TuinenBalkon\AffiliateLinkChecker\Bol\AffiliateLink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AffiliateLinkAdapterInterface {

	public function is_available(): bool;

	public function get_plugin_name(): string;

	public function get_link_url( int $link_id ): ?string;

	public function get_link_redirect_url( int $link_id ): ?string;

	public function get_all_links(): array;

	public function find_link_by_url( string $url ): ?int;

	public function get_links_by_host( string $host ): array;

	public function get_admin_edit_url( int $link_id ): ?string;

	public function get_links_by_name(): array;

	public function build_bol_params_index(): array;
}
