<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsService {

	private ApiClient $api_client;
	private ?array $cached_sites = null;

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
		register_setting( $options_group, 'tbmm_bol_marketing_credentials', array( $this, 'sanitize_credentials' ) );

		$page = 'bol-affiliate-insights-settings';

		add_settings_section( 'bol_api_credentials_section', 'Reporting API — Credentials', array( $this, 'render_api_credentials_section_text' ), $page );
		add_settings_field( 'bol_client_id', 'Client ID', array( $this, 'render_client_id_field' ), $page, 'bol_api_credentials_section', array( 'label_for' => 'bol_client_id_field' ) );
		add_settings_field( 'bol_client_secret', 'Client Secret', array( $this, 'render_client_secret_field' ), $page, 'bol_api_credentials_section', array( 'label_for' => 'bol_client_secret_field' ) );

		add_settings_section( 'bol_data_filters_section', 'Data Filters', array( $this, 'render_data_filters_section_text' ), $page );
		add_settings_field( 'bol_selected_website', 'Actieve website', array( $this, 'render_selected_website_field' ), $page, 'bol_data_filters_section', array( 'label_for' => 'bol_selected_website_field' ) );

		add_settings_section( 'bol_linkgenerator_section', 'Linkgenerator — handmatig Site ID', array( $this, 'render_linkgenerator_section_text' ), $page );
		add_settings_field( 'tbmm_bol_site_id', 'Handmatig Site ID', array( $this, 'render_site_id_field' ), $page, 'bol_linkgenerator_section', array( 'label_for' => 'tbmm_bol_site_id_field' ) );

		add_settings_section( 'bol_marketing_api_section', 'Marketing Catalog API — Credentials', array( $this, 'render_marketing_api_section_text' ), $page );
		add_settings_field( 'tbmm_marketing_client_id', 'Client ID', array( $this, 'render_marketing_client_id_field' ), $page, 'bol_marketing_api_section', array( 'label_for' => 'tbmm_marketing_client_id_field' ) );
		add_settings_field( 'tbmm_marketing_client_secret', 'Client Secret', array( $this, 'render_marketing_client_secret_field' ), $page, 'bol_marketing_api_section', array( 'label_for' => 'tbmm_marketing_client_secret_field' ) );

		add_settings_section( 'bol_debug_section', 'Debug & Logging', array( $this, 'render_debug_section_text' ), $page );
		add_settings_field( 'bol_debug_enabled', 'Enable debug logging', array( $this, 'render_debug_enabled_field' ), $page, 'bol_debug_section', array( 'label_for' => 'bol_debug_enabled_field' ) );
	}

	public function render_api_credentials_section_text(): void {
		echo '<p>Vul hieronder je Bol.com API Client ID en Client Secret in. Na het opslaan worden je gekoppelde websites automatisch opgehaald.</p>';
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

	public function render_data_filters_section_text(): void {
		echo '<p>Selecteer de website waarvoor je data wilt bekijken. Deze keuze filtert alle rapportagetabs én bepaalt het Site ID dat de Linkgenerator gebruikt om affiliate-links te bouwen.</p>';
	}

	private function get_sites(): array {
		if ( $this->cached_sites === null ) {
			$fetched            = $this->api_client->get_available_sites();
			$this->cached_sites = ( is_array( $fetched ) && ! empty( $fetched ) ) ? $fetched : array();
		}
		return $this->cached_sites;
	}

	public function render_selected_website_field(): void {
		$current_value = get_option( 'bol_affiliate_insights_selected_website', 'all_sites' );
		$sites         = $this->get_sites();

		echo "<select id='bol_selected_website_field' name='bol_affiliate_insights_selected_website'>";
		echo "<option value='all_sites'" . selected( $current_value, 'all_sites', false ) . ">Alle websites</option>";

		if ( ! empty( $sites ) ) {
			foreach ( $sites as $site_code => $site_name ) {
				echo "<option value='" . esc_attr( $site_code ) . "'" . selected( $current_value, $site_code, false ) . ">"
					. esc_html( $site_name ) . ' — Site ID: ' . esc_html( $site_code )
					. "</option>";
			}
		}
		echo "</select>";

		if ( ! empty( $sites ) ) {
			if ( $current_value === 'all_sites' && count( $sites ) > 1 ) {
				echo "<p class='description' style='color:#d63638;'>⚠ Kies een specifieke website zodat de Linkgenerator het juiste Site ID kan gebruiken. Bij meerdere websites kun je in de generator alsnog wisselen.</p>";
			} elseif ( $current_value !== 'all_sites' ) {
				echo "<p class='description'>✔ Linkgenerator gebruikt automatisch Site ID <strong>" . esc_html( $current_value ) . "</strong>.</p>";
			}
		} else {
			echo "<p class='description'>Geen websites opgehaald — controleer je API-credentials of vul het Site ID handmatig in (zie sectie hieronder).</p>";
		}
	}

	public function render_linkgenerator_section_text(): void {
		echo '<p>Alleen nodig als je <strong>geen API-koppeling</strong> hebt of als de website-dropdown hierboven op "Alle websites" staat en je maar één site hebt. '
			. 'Als er een specifieke website is geselecteerd via de API, heeft die altijd voorrang.</p>';
	}

	public function render_site_id_field(): void {
		$value            = get_option( 'tbmm_bol_site_id', '' );
		$selected_website = get_option( 'bol_affiliate_insights_selected_website', 'all_sites' );
		$sites            = $this->get_sites();
		$auto_resolved    = ( $selected_website !== 'all_sites' ) || ( ! empty( $sites ) && count( $sites ) === 1 );

		echo "<input type='text' id='tbmm_bol_site_id_field' name='tbmm_bol_site_id' value='" . esc_attr( $value ) . "' class='regular-text' placeholder='bijv. 10048'"
			. ( $auto_resolved ? " style='opacity:0.5;'" : '' ) . ">";

		if ( $auto_resolved ) {
			echo "<p class='description'>Niet nodig: Site ID wordt automatisch bepaald via de API-koppeling.</p>";
		} else {
			echo "<p class='description'>Je Site ID vind je in het <a href='https://partnerplatform.bol.com' target='_blank' rel='noopener'>bol.com partnerplatform</a> onder je website-instellingen.</p>";
		}
	}

	public function render_marketing_api_section_text(): void {
		echo '<p>Credentials voor de <strong>Bol.com Marketing Catalog API</strong>. Deze API geeft toegang tot productdata (prijs, afbeeldingen, ratings, varianten) op basis van EAN-nummers. '
			. 'Aanvragen via het <a href="https://partnerplatform.bol.com" target="_blank" rel="noopener">bol.com partnerplatform</a> onder Open API → Marketing Catalog API.</p>';
	}

	public function render_marketing_client_id_field(): void {
		$options = get_option( 'tbmm_bol_marketing_credentials', array() );
		$value   = $options['client_id'] ?? '';
		echo "<input type='text' id='tbmm_marketing_client_id_field' name='tbmm_bol_marketing_credentials[client_id]' value='" . esc_attr( $value ) . "' class='regular-text' autocomplete='off'>";
	}

	public function render_marketing_client_secret_field(): void {
		$options = get_option( 'tbmm_bol_marketing_credentials', array() );
		$value   = $options['client_secret'] ?? '';
		echo "<input type='password' id='tbmm_marketing_client_secret_field' name='tbmm_bol_marketing_credentials[client_secret]' value='" . esc_attr( $value ) . "' class='regular-text' autocomplete='off'>";
		echo "<p class='description'>Wordt alleen opgeslagen in je WordPress-database en nooit meegestuurd naar GitHub.</p>";
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

	public function sanitize_credentials( $input ): array {
		if ( ! is_array( $input ) ) {
			return array( 'client_id' => '', 'client_secret' => '' );
		}
		return array(
			'client_id'     => isset( $input['client_id'] ) ? sanitize_text_field( $input['client_id'] ) : '',
			'client_secret' => isset( $input['client_secret'] ) ? sanitize_text_field( $input['client_secret'] ) : '',
		);
	}

	public function sanitize_debug_flag( $value ): int {
		return ! empty( $value ) ? 1 : 0;
	}
}
