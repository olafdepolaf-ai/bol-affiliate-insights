<?php

namespace TuinenBalkon\AffiliateLinkChecker\Admin;

use TuinenBalkon\AffiliateLinkChecker\Service\LinkScanner;

class AjaxHandlerService {

	private LinkScanner $link_scanner;

	public function __construct( LinkScanner $link_scanner ) {
		$this->link_scanner = $link_scanner;
		add_action( 'wp_ajax_alc_check_link', [ $this, 'handle_check_link' ] );
	}

	public function handle_check_link(): void {
		check_ajax_referer( 'alc_run_scan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Geen toegang.' ], 403 );
		}

		$link_id  = isset( $_POST['link_id'] ) ? (int) $_POST['link_id'] : 0;
		$link_url = isset( $_POST['link_url'] ) ? esc_url_raw( wp_unslash( $_POST['link_url'] ) ) : '';

		if ( ! $link_id || ! $link_url ) {
			wp_send_json_error( [ 'message' => 'Ongeldige parameters.' ] );
		}

		$result = $this->link_scanner->check_single( $link_id, $link_url );

		wp_send_json_success( $result );
	}
}
