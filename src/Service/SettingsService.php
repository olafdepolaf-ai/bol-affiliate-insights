<?php
namespace TuinenBalkon\BolAffiliateInsights\Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SettingsService {
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function register_settings() {
        $options_group_name = 'bol_affiliate_insights_options_group'; 
        $option_name = 'bol_affiliate_insights_credentials'; 

        register_setting(
            $options_group_name,
            $option_name,
            array( $this, 'sanitize_credentials' ) 
        );

        register_setting(
            $options_group_name, 
            'bol_affiliate_insights_selected_website',
            'sanitize_text_field' 
        );

        add_settings_section(
            'bol_api_credentials_section', 
            'API Credentials',             
            array( $this, 'render_api_credentials_section_text' ), 
            'bol-affiliate-insights-settings' 
        );

        add_settings_field(
            'bol_client_id',                  
            'Client ID',                      
            array( $this, 'render_client_id_field' ), 
            'bol-affiliate-insights-settings',         
            'bol_api_credentials_section',    
            array( 'label_for' => 'bol_client_id_field' ) 
        );

        add_settings_field(
            'bol_client_secret',              
            'Client Secret',                  
            array( $this, 'render_client_secret_field' ), 
            'bol-affiliate-insights-settings',         
            'bol_api_credentials_section',    
            array( 'label_for' => 'bol_client_secret_field' ) 
        );

        add_settings_section(
            'bol_data_filters_section',
            'Data Filters',
            array( $this, 'render_data_filters_section_text' ),
            'bol-affiliate-insights-settings' 
        );

        add_settings_field(
            'bol_selected_website',
            'Filter data by Website',
            array( $this, 'render_selected_website_field' ),
            'bol-affiliate-insights-settings',
            'bol_data_filters_section',
            array( 'label_for' => 'bol_selected_website_field' )
        );
    }

    public function render_data_filters_section_text() {
        echo '<p>Select a website to filter the displayed report data across the plugin. This filter applies to the dashboard, report tabs, and charts.</p>';
    }
    
    public function render_selected_website_field() {
        $current_value = get_option( 'bol_affiliate_insights_selected_website', 'all_sites' );
        $api_client = \TuinenBalkon\BolAffiliateInsights\Plugin::get_instance()->get_api_client();
        $sites = array(); 

        if ($api_client) {
            $fetched_sites = $api_client->get_available_sites();
            if (is_array($fetched_sites)) {
                $sites = $fetched_sites;
            } else {
                 echo '<p class="notice notice-warning">Could not fetch available sites. API client might not be configured or there was an error.</p>';
            }
        } else {
            echo '<p class="notice notice-warning">API Client not available. Please configure API credentials first.</p>';
        }

        echo "<select id='bol_selected_website_field' name='bol_affiliate_insights_selected_website'>";
        echo "<option value='all_sites'" . selected( $current_value, 'all_sites', false ) . ">All Sites</option>";

        if ( ! empty( $sites ) ) {
            foreach ( $sites as $site_code => $site_name ) {
                echo "<option value='" . esc_attr( $site_code ) . "'" . selected( $current_value, $site_code, false ) . ">" . esc_html( $site_name ) . " (" . esc_html($site_code) . ")</option>";
            }
        } elseif (empty($sites) && $api_client && is_array($fetched_sites)) { 
             echo "<option value='' disabled>No individual sites found.</option>";
        }
        echo "</select>";
        echo "<p class='description'>If 'All Sites' is selected, data from all your registered websites will be shown.</p>";
    }


    public function render_api_credentials_section_text() {
        echo '<p>Enter your Bol.com API Client ID and Client Secret below.</p>';
    }

    public function render_client_id_field() {
        $options = get_option( 'bol_affiliate_insights_credentials' );
        $value = isset( $options['client_id'] ) ? $options['client_id'] : '';
        echo "<input type='text' id='bol_client_id_field' name='bol_affiliate_insights_credentials[client_id]' value='" . esc_attr( $value ) . "' class='regular-text'>";
    }

    public function render_client_secret_field() {
        $options = get_option( 'bol_affiliate_insights_credentials' );
        $value = isset( $options['client_secret'] ) ? $options['client_secret'] : '';
        echo "<input type='password' id='bol_client_secret_field' name='bol_affiliate_insights_credentials[client_secret]' value='" . esc_attr( $value ) . "' class='regular-text'>";
    }

    public function sanitize_credentials( $input ) {
        $sanitized_input = array();
        $sanitized_input['client_id'] = isset( $input['client_id'] ) ? sanitize_text_field( $input['client_id'] ) : '';
        $sanitized_input['client_secret'] = isset( $input['client_secret'] ) ? sanitize_text_field( $input['client_secret'] ) : '';
        return $sanitized_input;
    }
}
