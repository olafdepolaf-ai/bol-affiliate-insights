<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsService {

	private ApiClient $api_client;

	public function __construct( ApiClient $api_client ) {
		$this->api_client = $api_client;
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		$options_group = 'bol_affiliate_insights_options_group';

		register_setting( $options_group, 'bol_affiliate_insights_credentials', array( $this, 'sanitize_credentials' ) );
		register_setting( $options_group, 'bol_affiliate_insights_selected_website', 'sanitize_text_field' );
		register_setting( $options_group, 'bol_affiliate_insights_debug_enabled', array( $this, 'sanitize_debug_flag' ) );
		register_setting( $options_group, 'tbmm_bol_site_id', 'sanitize_text_field' );

		$page = 'bol-affiliate-insights-settings';

		add_settings_section( 'bol_api_credentials_section', 'API Credentials', array( $this, 'render_api_credentials_section_text' ), $page );
		add_settings_field( 'bol_client_id', 'Client ID', array( $this, 'render_client_id_field' ), $page, 'bol_api_credentials_section', array( 'label_for' => 'bol_client_id_field' ) );
		add_settings_field( 'bol_client_secret', 'Client Secret', array( $this, 'render_client_secret_field' ), $page, 'bol_api_credentials_section', array( 'label_for' => 'bol_client_secret_field' ) );
		add_settings_field( 'tbmm_bol_site_id', 'Affiliate Site ID', array( $this, 'render_site_id_field' ), $page, 'bol_api_credentials_section', array( 'label_for' => 'tbmm_bol_site_id_field' ) );

		add_settings_section( 'bol_data_filters_section', 'Data Filters', array( $this, 'render_data_filters_section_text' ), $page );
		add_settings_field( 'bol_selected_website', 'Filter data by Website', array( $this, 'render_selected_website_field' ), $page, 'bol_data_filters_section', array( 'label_for' => 'bol_selected_website_field' ) );

		add_settings_section( 'bol_debug_section', 'Debug & Logging', array( $this, 'render_debug_section_text' ), $page );
		add_settings_field( 'bol_debug_enabled', 'Enable debug logging', array( $this, 'render_debug_enabled_field' ), $page, 'bol_debug_section', array( 'label_for' => 'bol_debug_enabled_field' ) );
	}

	public function render_api_credentials_section_text(): void {
		echo '<p>Enter your Bol.com API Client ID and Client Secret below.</p>';
	}

	public function render_client_id_field(): void {
		$options = get_option( 'bol_affiliate_insights_credentials' );
		$value   = isset( $options['client_id'] ) ? $options['client_id'] : '';
		echo "<input type='text' id='bol_client_id_field' name='bol_affiliate_insights_credentials[client_id]' value='" . esc_attr( $value ) . "' class='regular-text'>";
	}

	public function render_client_secret_field(): void {
		$options = get_option( 'bol_affiliate_insights_credentials' );
		$value   = isset( $options['client_secret'] ) ? $options['client_secret'] : '';
		echo "<input type='password' id='bol_client_secret_field' name='bol_affiliate_insights_credentials[client_secret]' value='" . esc_attr( $value ) . "' class='regular-text'>";
	}

	public function render_site_id_field(): void {
		$value = get_option( 'tbmm_bol_site_id', '' );
		echo "<input type='text' id='tbmm_bol_site_id_field' name='tbmm_bol_site_id' value='" . esc_attr( $value ) . "' class='regular-text' placeholder='bijv. 10048'>";
		echo "<p class='description'>Je unieke Site ID voor de <a href='https://partner.bol.com' target='_blank' rel='noopener'>bol.com partner.bol.com</a> tracking-links (te vinden in je partnerportaal).</p>";
	}

	public function render_data_filters_section_text(): void {
		echo '<p>Select a website to filter the displayed report data across the plugin. This filter applies to the dashboard, report tabs, and charts.</p>';
	}

	public function render_selected_website_field(): void {
		$current_value = get_option( 'bol_affiliate_insights_selected_website', 'all_sites' );
		$sites         = $this->api_client->get_available_sites();

		echo "<select id='bol_selected_website_field' name='bol_affiliate_insights_selected_website'>";
		echo "<option value='all_sites'" . selected( $current_value, 'all_sites', false ) . ">All Sites</option>";

		if ( ! empty( $sites ) ) {
			foreach ( $sites as $site_code => $site_name ) {
				echo "<option value='" . esc_attr( $site_code ) . "'" . selected( $current_value, $site_code, false ) . ">" . esc_html( $site_name ) . " (" . esc_html( $site_code ) . ")</option>";
			}
		}
		echo "</select>";
		echo "<p class='description'>If 'All Sites' is selected, data from all your registered websites will be shown.</p>";
	}

	public function render_debug_section_text(): void {
		echo '<p>Enable debug logging only when you are troubleshooting. It may log additional technical details to the PHP error log.</p>';
	}

	public function render_debug_enabled_field(): void {
		$enabled = (bool) get_option( 'bol_affiliate_insights_debug_enabled', false );
		echo "<label for='bol_debug_enabled_field'>";
		echo "<input type='checkbox' id='bol_debug_enabled_field' name='bol_affiliate_insights_debug_enabled' value='1' " . checked( $enabled, true, false ) . " />";
		echo ' Log extra debug information to the PHP error log';
		echo '</label>';
	}

	public function sanitize_credentials( array $input ): array {
		return array(
			'client_id'     => isset( $input['client_id'] ) ? sanitize_text_field( $input['client_id'] ) : '',
			'client_secret' => isset( $input['client_secret'] ) ? sanitize_text_field( $input['client_secret'] ) : '',
		);
	}

	public function sanitize_debug_flag( $value ): int {
		return ! empty( $value ) ? 1 : 0;
	}
}
