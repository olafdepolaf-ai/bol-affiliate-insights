<?php
/**
 * Bol_Affiliate_Insights_Settings Class
 *
 * Handles the plugin settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Bol_Affiliate_Insights_Settings' ) ) {
    /**
     * Bol_Affiliate_Insights_Settings Class
     *
     * Handles the plugin's admin settings page, including tabbed navigation,
     * API credential management, AJAX handlers for testing connections and fetching chart data,
     * and enqueuing necessary scripts and styles for the settings page.
     */
    class Bol_Affiliate_Insights_Settings {

        /**
         * Stores the hook suffix of the main settings page.
         * Used to conditionally load scripts and styles only on the plugin's admin page.
         *
         * @var string|false
         */
        private $settings_page_hook_suffix;

        /**
         * Constructor for Bol_Affiliate_Insights_Settings.
         *
         * Hooks into WordPress actions to add the admin menu, register settings,
         * set up AJAX handlers, and enqueue admin scripts.
         */
        public function __construct() {
            // Add the main admin menu page for the plugin.
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            // Register plugin settings with WordPress Settings API.
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            // Register AJAX handler for testing API connection.
            add_action( 'wp_ajax_bol_test_connection', array( $this, 'handle_test_connection_ajax' ) );
            // Register AJAX handler for fetching chart data for the dashboard.
            add_action( 'wp_ajax_bol_fetch_chart_data', array( $this, 'handle_fetch_chart_data_ajax' ) );
            // AJAX handler for fetching available sites for settings page
            add_action( 'wp_ajax_bol_fetch_available_sites', array( $this, 'handle_fetch_available_sites_ajax' ) );
            // Enqueue scripts and styles specific to the plugin's admin page.
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
            // Add action to handle screen options.
            // Note: The subtask initially suggested an 'admin_init' hook for a method to set up screen options,
            // but then corrected that the 'load-{$hook_suffix}' action should be added inside 'add_admin_menu'.
            // So, the actual hook for handle_screen_options() will be added in add_admin_menu().
            // No new action is added here in __construct directly for handle_screen_options itself.
        }

        /**
         * Handles the AJAX request for fetching chart data for the dashboard.
         *
         * Validates the AJAX nonce, retrieves request parameters (metric, period),
         * and currently returns placeholder data for the chart. Real data fetching
         * logic is to be implemented.
         *
         * @return void Outputs JSON response (success or error) and dies using wp_send_json_*.
         */
        public function handle_fetch_chart_data_ajax() {
            check_ajax_referer( 'bol_chart_data_nonce', 'nonce' ); 

            $metric = isset( $_POST['metric'] ) ? sanitize_key( $_POST['metric'] ) : 'orders';
            $period = isset( $_POST['period'] ) ? sanitize_key( $_POST['period'] ) : 'last_4_weeks'; 
            $granularity = isset( $_POST['granularity'] ) ? sanitize_key( $_POST['granularity'] ) : 'auto';
            $chart_specific_site_filter = isset( $_POST['site'] ) ? sanitize_key( $_POST['site'] ) : 'all_sites';

            // error_log('BOL Chart AJAX: Request received. Metric=' . $metric . ', Period=' . $period . ', Granularity=' . $granularity . ', SiteFilter=' . $chart_specific_site_filter);

            $global_selected_site = get_option('bol_affiliate_insights_selected_website', 'all_sites');
            $effective_site_filter = $global_selected_site !== 'all_sites' ? $global_selected_site : $chart_specific_site_filter;
            // error_log('BOL Chart AJAX: Effective site filter: ' . $effective_site_filter);

            $dataset_label = ucfirst( str_replace( '_', ' ', $metric ) );
            if ($metric === 'conversion') { 
                $dataset_label = 'Conversion Rate';
            }

            $chart_data = array(
                'labels' => array(),
                'datasets' => array(
                    array(
                        'label' => $dataset_label,
                        'data' => array(),
                        'backgroundColor' => 'rgba(0, 115, 170, 0.5)',
                        'borderColor' => 'rgba(0, 115, 170, 1)',
                        'borderWidth' => 1,
                        'tension' => 0.1 
                    )
                )
            );
            $error_message = null;
            $raw_api_items = array(); 

            $api_client = Bol_Affiliate_Insights_Plugin::get_instance()->get_api_client();
            if (!$api_client) {
                // error_log('BOL Chart AJAX: API Client NOT AVAILABLE!');
                wp_send_json_error(array('message' => 'API Client not available. Please configure API credentials.'));
                return;
            }
            // error_log('BOL Chart AJAX: API Client seems available.');

            $start_date_obj = new DateTimeImmutable('now', wp_timezone()); 
            $end_date_obj = new DateTimeImmutable('now', wp_timezone());   
            $effective_granularity = $granularity; 

            try {
                switch ($period) {
                    case 'last_4_weeks': 
                        $end_date_obj = new DateTimeImmutable('yesterday', wp_timezone());
                        $start_date_obj = (clone $end_date_obj)->modify('-27 days'); 
                        break;
                    case 'this_year':
                        $start_date_obj = new DateTimeImmutable(date('Y-01-01'), wp_timezone());
                        $end_date_obj = new DateTimeImmutable(date('Y-12-31'), wp_timezone());
                        break;
                    case 'last_year':
                        $last_year = (int)date('Y') - 1;
                        $start_date_obj = new DateTimeImmutable($last_year . '-01-01', wp_timezone());
                        $end_date_obj = new DateTimeImmutable($last_year . '-12-31', wp_timezone());
                        break;
                    case 'this_month':
                        $start_date_obj = new DateTimeImmutable('first day of this month', wp_timezone());
                        $end_date_obj = new DateTimeImmutable('last day of this month', wp_timezone());
                        break;
                    case 'last_month':
                        $start_date_obj = new DateTimeImmutable('first day of last month', wp_timezone());
                        $end_date_obj = new DateTimeImmutable('last day of last month', wp_timezone());
                        break;
                    case 'last_30_days':
                        $end_date_obj = new DateTimeImmutable('yesterday', wp_timezone());
                        $start_date_obj = $end_date_obj->modify('-29 days');
                        break;
                    case 'last_7_days':
                        $end_date_obj = new DateTimeImmutable('yesterday', wp_timezone());
                        $start_date_obj = $end_date_obj->modify('-6 days');
                        break;
                    case 'today':
                        break;
                    case 'entire_period': 
                    default: 
                        $start_date_obj = new DateTimeImmutable(date('Y-01-01'), wp_timezone());
                        $end_date_obj = new DateTimeImmutable(date('Y-12-31'), wp_timezone());
                        $period = 'this_year'; 
                        break;
                }
            } catch (Exception $e) {
                $error_message = 'Error calculating date range: ' . $e->getMessage();
                // error_log('BOL Chart AJAX: Date Range Exception: ' . $error_message);
                wp_send_json_error(array('message' => $error_message));
                return;
            }
            // error_log('BOL Chart AJAX: Calculated Date Range: START=' . $start_date_obj->format('Y-m-d') . ' END=' . $end_date_obj->format('Y-m-d'));
            
            $diff_days = $end_date_obj->diff($start_date_obj)->days;

            if ($granularity === 'auto') {
                if ($period === 'this_year' || $period === 'last_year' || $period === 'entire_period') {
                    $effective_granularity = 'month';
                } elseif ($diff_days <= 42) { 
                    $effective_granularity = 'day';
                } elseif ($diff_days <= 365) { 
                    $effective_granularity = 'week';
                } else { 
                    $effective_granularity = 'month';
                }
            } else { 
                if ($granularity === 'day' && $diff_days > 90) {
                    $effective_granularity = 'week'; 
                } elseif ($granularity === 'week' && $diff_days > 730) { 
                    $effective_granularity = 'month'; 
                }
            }
            // error_log('BOL Chart AJAX: Effective Granularity: ' . $effective_granularity . ' (Original: ' . $granularity . ', DiffDays: ' . $diff_days . ')');

            $week_periods_for_lookup = array(); 
            $current_loop_date = clone $start_date_obj; 

            switch ($effective_granularity) {
                case 'month':
                    $interval = new DateInterval('P1M');
                    $period_start_iterate_month = $current_loop_date->modify('first day of this month');
                    $period_end_iterate = (clone $end_date_obj)->modify('first day of this month next month'); 
                    $date_period_iterator = new DatePeriod($period_start_iterate_month, $interval, $period_end_iterate);
                    $year_of_start = $start_date_obj->format('Y');
                    $year_of_end = $end_date_obj->format('Y');
                    foreach ($date_period_iterator as $date_point) {
                        $chart_data['labels'][] = ($year_of_start === $year_of_end) ? $date_point->format('M') : $date_point->format('M Y');
                    }
                    break;
                case 'week':
                    $interval = new DateInterval('P1W');
                    $period_start_iterate_week = $current_loop_date->modify('monday this week');
                    $period_end_iterate = (clone $end_date_obj)->modify('monday next week');
                    $date_period_iterator = new DatePeriod($period_start_iterate_week, $interval, $period_end_iterate);
                    foreach ($date_period_iterator as $date_point) {
                        $week_start_dt = clone $date_point; 
                        $week_end_dt = (clone $date_point)->modify('+6 days')->setTime(23,59,59); 
                        $actual_week_start_dt = ($week_start_dt < $start_date_obj) ? $start_date_obj->setTime(0,0,0) : $week_start_dt;
                        $actual_week_end_dt = ($week_end_dt > $end_date_obj) ? $end_date_obj->setTime(23,59,59) : $week_end_dt;
                        if ($actual_week_start_dt <= $actual_week_end_dt) {
                            $chart_data['labels'][] = $actual_week_start_dt->format('M d') . ' - ' . $actual_week_end_dt->format('M d Y'); 
                            $week_periods_for_lookup[] = array('start_dt' => $actual_week_start_dt, 'end_dt' => $actual_week_end_dt);
                        }
                    }
                    break;
                case 'day':
                    $interval = new DateInterval('P1D');
                    $period_end_iterate = (clone $end_date_obj)->modify('+1 day'); 
                    $date_period_iterator = new DatePeriod($current_loop_date, $interval, $period_end_iterate);
                    $show_year = ($start_date_obj->format('Y') !== $end_date_obj->format('Y'));
                    foreach ($date_period_iterator as $date_point) {
                        $chart_data['labels'][] = $show_year ? $date_point->format('M d Y') : $date_point->format('M d');
                    }
                    break;
            }
            // error_log('BOL Chart AJAX: Generated Labels Count: ' . count($chart_data['labels']));
            
            if (!empty($chart_data['labels'])) {
                $chart_data['datasets'][0]['data'] = array_fill(0, count($chart_data['labels']), 0);
            }
            
            $start_date_str = $start_date_obj->format('Y-m-d');
            $end_date_str = $end_date_obj->format('Y-m-d');

            if ($metric === 'commission') {
                $response = $api_client->get_orders_report($start_date_str, $end_date_str);
                if (is_wp_error($response)) {
                    $error_message = 'Error fetching orders report: ' . $response->get_error_message();
                } elseif (isset($response['items'])) {
                    $raw_api_items = $response['items'];
                } else {
                    $error_message = 'Orders report data is not in the expected format.';
                }
            } elseif (in_array($metric, array('orders', 'clicks', 'revenue', 'conversion'))) {
                $response = $api_client->get_promotion_methods_report($start_date_str, $end_date_str);
                if (is_wp_error($response)) {
                    $error_message = 'Error fetching promotion methods report: ' . $response->get_error_message();
                } elseif (isset($response['items'])) {
                    $raw_api_items = $response['items'];
                    if ($effective_site_filter !== 'all_sites' && !empty($raw_api_items)) {
                        $raw_api_items = array_filter($raw_api_items, function ($item) use ($effective_site_filter) {
                            return isset($item['siteCode']) && $item['siteCode'] == $effective_site_filter;
                        });
                    }
                } else {
                    $error_message = 'Promotion methods report data is not in the expected format.';
                }
            } else {
                $error_message = 'Invalid metric selected for chart data.';
            }

            if ($error_message) {
                // error_log('BOL Chart AJAX: Error message set after API fetch: ' . $error_message);
                wp_send_json_error(array('message' => $error_message));
                return;
            }
            
            // error_log('BOL Chart AJAX: Raw API Items Count for metric "' . $metric . '": ' . count($raw_api_items) . '. Using StartDate=' . $start_date_str . ' EndDate=' . $end_date_str);
            // if (count($raw_api_items) > 0 && count($raw_api_items) < 10) { 
            //     error_log('BOL Chart AJAX: Sample Raw API Items: ' . print_r(array_slice($raw_api_items, 0, 3), true));
            // } elseif (count($raw_api_items) == 0) {
            //     error_log('BOL Chart AJAX: NO ITEMS RETURNED FROM API for metric "' . $metric . '"');
            // }

            if (!empty($raw_api_items) && !empty($chart_data['labels'])) {
                // error_log('BOL Chart AJAX: Starting aggregation loop. Number of items: ' . count($raw_api_items) . '. Number of labels: ' . count($chart_data['labels']));
                $items_processed_in_loop = 0; 
                $items_added_to_data = 0;

                foreach ($raw_api_items as $item_index => $item) { 
                    // if ($item_index < 3) { 
                    //     error_log('BOL Chart AJAX: Loop item #' . $item_index . ' data: ' . print_r($item, true));
                    // }
                    try {
                        $item_date_str = '';
                        if ($metric === 'commission' && isset($item['orderDateTime'])) { 
                            $item_date_str = $item['orderDateTime']; 
                        } elseif (in_array($metric, array('orders', 'clicks', 'revenue', 'conversion')) && isset($item['date'])) { // CORRECTED KEY to 'date'
                            $item_date_str = $item['date'];                                                                      // CORRECTED KEY to 'date'
                        }

                        if (empty($item_date_str)) {
                            // if ($item_index < 5) error_log('BOL Chart AJAX: Loop item #' . $item_index . ' for metric "' . $metric . '" SKIPPED due to empty item_date_str.');
                            continue;
                        }

                        $item_datetime = new DateTimeImmutable($item_date_str, wp_timezone());
                        $label_index = -1;

                        switch ($effective_granularity) {
                            case 'month':
                                $item_month_year_label = $item_datetime->format('M Y');
                                $item_month_label = $item_datetime->format('M');
                                if (in_array($item_month_year_label, $chart_data['labels'])) {
                                    $label_index = array_search($item_month_year_label, $chart_data['labels']);
                                } elseif (in_array($item_month_label, $chart_data['labels'])) {
                                     $label_index = array_search($item_month_label, $chart_data['labels']);
                                }
                                break;
                            case 'week':
                                foreach ($week_periods_for_lookup as $idx => $week_period) {
                                    if ($item_datetime >= $week_period['start_dt'] && $item_datetime <= $week_period['end_dt']) {
                                        $label_index = $idx;
                                        break;
                                    }
                                }
                                break;
                            case 'day':
                                $item_day_label_my = $item_datetime->format('M d Y');
                                $item_day_label_md = $item_datetime->format('M d');
                                if (in_array($item_day_label_my, $chart_data['labels'])) {
                                   $label_index = array_search($item_day_label_my, $chart_data['labels']);
                                } elseif (in_array($item_day_label_md, $chart_data['labels'])) {
                                   $label_index = array_search($item_day_label_md, $chart_data['labels']);
                                }
                                break;
                        }
                        
                        // if ($item_index < 5) error_log('BOL Chart AJAX: Loop item #' . $item_index . ' (metric: '.$metric.', date: '.$item_datetime->format('Y-m-d').') determined label_index: ' . $label_index);

                        if ($label_index !== -1) {
                            $items_processed_in_loop++;
                            $value_to_add = 0;
                            $added_flag = false;

                            if ($metric === 'commission' && isset($item['commission'])) { 
                                $value_to_add = (float)$item['commission'];
                                $chart_data['datasets'][0]['data'][$label_index] += $value_to_add;
                                $added_flag = true;
                            } elseif ($metric === 'orders' && isset($item['orders'])) { 
                                $value_to_add = (int)$item['orders'];
                                $chart_data['datasets'][0]['data'][$label_index] += $value_to_add;
                                $added_flag = true;
                            } elseif ($metric === 'clicks' && isset($item['clicks'])) { 
                                $value_to_add = (int)$item['clicks'];
                                $chart_data['datasets'][0]['data'][$label_index] += $value_to_add;
                                $added_flag = true;
                            } elseif ($metric === 'revenue' && isset($item['revenueInclVat'])) { 
                                $value_to_add = (float)$item['revenueInclVat'];
                                $chart_data['datasets'][0]['data'][$label_index] += $value_to_add;
                                $added_flag = true;
                            }
                            
                            if ($added_flag) $items_added_to_data++;
                            // if ($item_index < 5 && $added_flag) error_log('BOL Chart AJAX: Loop item #' . $item_index . ' ADDED value ' . $value_to_add . ' to label_index ' . $label_index . '. New total: ' . $chart_data['datasets'][0]['data'][$label_index]);
                        }
                    } catch (Exception $e) {
                        // error_log('BOL Chart AJAX: EXCEPTION in loop for item #' . $item_index . ': ' . $e->getMessage() . ' Item: ' . print_r($item, true));
                        continue; 
                    }
                }
                // error_log('BOL Chart AJAX: Finished aggregation loop. Items processed with valid label_index: ' . $items_processed_in_loop . '. Items actually contributing to data sum: ' . $items_added_to_data);
                
                if ($metric === 'conversion') {
                    // error_log('BOL Chart AJAX: Handling "conversion" metric aggregation.');
                    $aggregated_orders = array_fill(0, count($chart_data['labels']), 0);
                    $aggregated_clicks = array_fill(0, count($chart_data['labels']), 0);

                    foreach ($raw_api_items as $item) { 
                         try {
                            $item_date_str = isset($item['date']) ? $item['date'] : ''; // Use 'date' for conversion too
                            if (empty($item_date_str)) continue;
                            $item_datetime = new DateTimeImmutable($item_date_str, wp_timezone());
                            $label_index = -1; 

                            switch ($effective_granularity) { 
                                case 'month':
                                    $item_month_year_label = $item_datetime->format('M Y');
                                    $item_month_label = $item_datetime->format('M');
                                    if (in_array($item_month_year_label, $chart_data['labels'])) {
                                        $label_index = array_search($item_month_year_label, $chart_data['labels']);
                                    } elseif (in_array($item_month_label, $chart_data['labels'])) {
                                        $label_index = array_search($item_month_label, $chart_data['labels']);
                                    }
                                    break;
                                case 'week':
                                    foreach ($week_periods_for_lookup as $idx => $week_period) {
                                        if ($item_datetime >= $week_period['start_dt'] && $item_datetime <= $week_period['end_dt']) {
                                            $label_index = $idx;
                                            break;
                                        }
                                    }
                                    break;
                                case 'day':
                                    $item_day_label_my = $item_datetime->format('M d Y');
                                    $item_day_label_md = $item_datetime->format('M d');
                                    if (in_array($item_day_label_my, $chart_data['labels'])) {
                                        $label_index = array_search($item_day_label_my, $chart_data['labels']);
                                    } elseif (in_array($item_day_label_md, $chart_data['labels'])) {
                                        $label_index = array_search($item_day_label_md, $chart_data['labels']);
                                    }
                                    break;
                            }

                            if ($label_index !== -1) {
                                $aggregated_orders[$label_index] += isset($item['orders']) ? (int)$item['orders'] : 0;
                                $aggregated_clicks[$label_index] += isset($item['clicks']) ? (int)$item['clicks'] : 0;
                            }
                        } catch (Exception $e) {
                            // error_log('BOL Chart AJAX: EXCEPTION processing item for conversion: ' . $e->getMessage() . ' Item: ' . print_r($item, true));
                            continue;
                        }
                    }
                    // error_log('BOL Chart AJAX: Conversion - Aggregated Orders: ' . print_r($aggregated_orders, true));
                    // error_log('BOL Chart AJAX: Conversion - Aggregated Clicks: ' . print_r($aggregated_clicks, true));

                    for ($i = 0; $i < count($chart_data['labels']); $i++) {
                        $chart_data['datasets'][0]['data'][$i] = ($aggregated_clicks[$i] > 0) ? round(($aggregated_orders[$i] / $aggregated_clicks[$i]) * 100, 2) : 0;
                    }
                    $error_message = null; 
                }
            } elseif (empty($raw_api_items)) {
                // error_log('BOL Chart AJAX: Skipped aggregation loop because raw_api_items is empty.');
            } elseif (empty($chart_data['labels'])) {
                // error_log('BOL Chart AJAX: Skipped aggregation loop because chart_data[labels] is empty.');
            }

            if (empty($chart_data['labels']) && !$error_message) {
                 $chart_data['labels'] = array('No data points for the selected period and granularity.');
                 $chart_data['datasets'][0]['data'] = array(0);
            }

            if ($error_message) {
                wp_send_json_error( array( 'message' => $error_message ) );
            } else {
                // error_log('BOL Chart AJAX: Sending success response. Final chart_data[datasets][0][data]: ' . print_r($chart_data['datasets'][0]['data'], true));
                wp_send_json_success( $chart_data );
            }
        }
        
        /**
         * Handles the AJAX request for testing the API connection.
         *
         * Validates the AJAX nonce, attempts to get an access token using
         * `Bol_API_Auth_Service`, and returns a JSON response indicating
         * success or failure.
         *
         * @return void Outputs JSON response (success or error) and dies using wp_send_json_*.
         */
        public function handle_test_connection_ajax() {
            check_ajax_referer( 'bol_test_connection_nonce', 'nonce' ); 

            if ( ! class_exists( 'Bol_API_Auth_Service' ) ) {
                wp_send_json_error( array( 'message' => 'ERROR: Authentication service class not found.' ), 500 );
                return; 
            }
            $auth_service = new Bol_API_Auth_Service();
            $token_data = $auth_service->get_access_token(); 

            if ( is_wp_error( $token_data ) ) {
                wp_send_json_error( array(
                    'message' => 'Connection Failed: ' . $token_data->get_error_message(),
                    'code' => $token_data->get_error_code()
                ) );
            } elseif ( $token_data ) {
                wp_send_json_success( array( 'message' => 'Connection Successful! Access token obtained.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Connection Failed: Unknown error retrieving access token.' ) );
            }
        }

        /**
         * Enqueues scripts and styles needed for the plugin's admin settings page.
         *
         * This method ensures that necessary JavaScript libraries (jQuery, Chart.js, jQuery UI Datepicker)
         * and custom CSS/JS are loaded only on the plugin's settings page.
         *
         * @param string $hook_suffix The hook suffix of the current admin page.
         * @return void
         */
        public function enqueue_admin_scripts( $hook_suffix ) {
            if ( $hook_suffix !== $this->settings_page_hook_suffix ) {
                return;
            }

            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(), 
                '3.9.1', 
                true     
            );

            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('wp-jquery-ui-dialog'); 

            wp_enqueue_style(
                'bol-admin-styles',
                plugins_url( '../assets/css/admin-styles.css', __FILE__ ),
                array(), 
                '0.1.1' // Incremented version for CSS changes
            );

            wp_enqueue_script(
                'bol-admin-settings-js',
                plugins_url( '../assets/js/admin-settings.js', __FILE__ ),
                array( 'jquery', 'chart-js', 'jquery-ui-datepicker' ), 
                '0.1.0', 
                true     
            );

            wp_localize_script( 'bol-admin-settings-js', 'bol_settings_params', array(
                'nonce' => wp_create_nonce( 'bol_test_connection_nonce' ), 
                'chart_nonce' => wp_create_nonce( 'bol_chart_data_nonce' ), 
                'fetch_sites_nonce' => wp_create_nonce( 'bol_fetch_sites_nonce' ) 
            ) );
        }

        /**
         * Handles AJAX request to fetch available sites.
         * Used to dynamically populate the site filter dropdown on the settings page.
         */
        public function handle_fetch_available_sites_ajax() {
            check_ajax_referer( 'bol_fetch_sites_nonce', 'nonce' );

            $api_client = Bol_Affiliate_Insights_Plugin::get_instance()->get_api_client();
            if ( ! $api_client ) {
                wp_send_json_error( array( 'message' => 'API Client not available.' ) );
                return;
            }

            $sites = $api_client->get_available_sites();
            if ( is_wp_error( $sites ) ) {
                wp_send_json_error( array( 'message' => 'Error fetching sites: ' . $sites->get_error_message() ) );
            } elseif ( empty( $sites ) ) {
                wp_send_json_success( array( 'sites' => array(), 'message' => 'No sites found or API access issue for sites.' ) );
            } else {
                wp_send_json_success( array( 'sites' => $sites ) );
            }
        }
        
        /**
         * Registers plugin settings using the WordPress Settings API.
         *
         * This method defines the settings group, option name, and registers
         * the settings fields and sections for API credentials.
         *
         * @return void
         */
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

        /**
         * Renders descriptive text for the Data Filters settings section.
         */
        public function render_data_filters_section_text() {
            echo '<p>Select a website to filter the displayed report data across the plugin. This filter applies to the dashboard, report tabs, and charts.</p>';
        }
        
        /**
         * Renders the dropdown field for selecting the website to filter by.
         */
        public function render_selected_website_field() {
            $current_value = get_option( 'bol_affiliate_insights_selected_website', 'all_sites' );
            $api_client = Bol_Affiliate_Insights_Plugin::get_instance()->get_api_client();
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


        /**
         * Renders descriptive text for the API credentials settings section.
         *
         * @return void
         */
        public function render_api_credentials_section_text() {
            echo '<p>Enter your Bol.com API Client ID and Client Secret below.</p>';
        }

        /**
         * Renders the input field for the API Client ID.
         *
         * Retrieves the current value from options and displays it in a text input.
         *
         * @return void
         */
        public function render_client_id_field() {
            $options = get_option( 'bol_affiliate_insights_credentials' );
            $value = isset( $options['client_id'] ) ? $options['client_id'] : '';
            echo "<input type='text' id='bol_client_id_field' name='bol_affiliate_insights_credentials[client_id]' value='" . esc_attr( $value ) . "' class='regular-text'>";
        }

        /**
         * Renders the input field for the API Client Secret.
         *
         * Retrieves the current value from options and displays it in a password input.
         *
         * @return void
         */
        public function render_client_secret_field() {
            $options = get_option( 'bol_affiliate_insights_credentials' );
            $value = isset( $options['client_secret'] ) ? $options['client_secret'] : '';
            echo "<input type='password' id='bol_client_secret_field' name='bol_affiliate_insights_credentials[client_secret]' value='" . esc_attr( $value ) . "' class='regular-text'>";
        }

        /**
         * Sanitizes the API credentials input before saving to the database.
         *
         * Ensures that the Client ID and Client Secret are plain text.
         *
         * @param array $input The raw input array from the settings form.
         * @return array The sanitized input array.
         */
        public function sanitize_credentials( $input ) {
            $sanitized_input = array();
            $sanitized_input['client_id'] = isset( $input['client_id'] ) ? sanitize_text_field( $input['client_id'] ) : '';
            $sanitized_input['client_secret'] = isset( $input['client_secret'] ) ? sanitize_text_field( $input['client_secret'] ) : '';
            return $sanitized_input;
        }

        /**
         * Adds the main admin menu page for the plugin.
         *
         * Registers a top-level menu item in the WordPress admin sidebar.
         * The page displays various reports and settings for the plugin.
         * Stores the page hook suffix in $this->settings_page_hook_suffix for conditional script loading.
         *
         * @return void
         */
        public function add_admin_menu() {
            $this->settings_page_hook_suffix = add_menu_page(
                'Bol Affiliate Insights',                           
                'Bol Insights',                                     
                'manage_options',                                   
                'bol-affiliate-insights',                           
                array( $this, 'render_settings_page' ),            
                plugins_url( 'assets/images/bol-logo.png', BOL_AFFILIATE_INSIGHTS_FILE ), // Corrected path for logo
                25                                                  
            );

            if ( $this->settings_page_hook_suffix ) {
                add_action( "load-{$this->settings_page_hook_suffix}", array( $this, 'handle_screen_options' ) );
            }
        }

        /**
         * Adds screen options, specifically the 'per_page' option for list tables.
         *
         * This method is hooked into the 'load-{$page_hook_suffix}' action, ensuring it runs
         * only on the plugin's settings page. It allows users to customize how many
         * items are shown per page in the WP_List_Table instances.
         */
        public function handle_screen_options() {
            // Ensure we are on the correct screen. This is mostly redundant due to the
            // specific load hook, but good for robustness.
            $screen = get_current_screen();

            // Check if the current screen ID matches our settings page's base ID.
            // The screen ID for a top-level menu page is 'toplevel_page_{menu_slug}'.
            // add_menu_page returns the hook directly, e.g. "toplevel_page_bol-affiliate-insights"
            if ( ! is_object( $screen ) || $screen->id !== $this->settings_page_hook_suffix ) {
                return;
            }

            // Define the arguments for the 'per_page' screen option.
            $args = array(
                'label'   => __('Items per page', 'bol-affiliate-insights'), // Translatable label
                'default' => 20, // Default number of items per page
                'option'  => 'bol_items_per_page' // The option name that will store the user's preference
            );
            add_screen_option( 'per_page', $args );

            // Determine active tab to load the correct list table columns for screen options
            $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
            $list_table = null;
            $table_columns = array();

            // Define path to includes directory
            // Assuming this file (class-bol-affiliate-insights-settings.php) is in the 'includes' directory.
            $includes_dir = plugin_dir_path( __FILE__ ); // This will be 'wp-content/plugins/your-plugin/includes/'

            if ( $current_tab === 'orders' ) {
                if ( ! class_exists( 'Bol_Orders_List_Table', false ) ) {
                    require_once $includes_dir . 'class-bol-orders-list-table.php';
                }
                if ( class_exists( 'Bol_Orders_List_Table', false ) ) {
                    $list_table = new Bol_Orders_List_Table();
                }
            } elseif ( $current_tab === 'commission_revenue' ) {
                if ( ! class_exists( 'Bol_Commission_Revenue_List_Table', false ) ) {
                    require_once $includes_dir . 'class-bol-commission-revenue-list-table.php';
                }
                if ( class_exists( 'Bol_Commission_Revenue_List_Table', false ) ) {
                    $list_table = new Bol_Commission_Revenue_List_Table();
                }
            } elseif ( $current_tab === 'promotion_methods' ) {
                if ( ! class_exists( 'Bol_Promotion_Methods_List_Table', false ) ) {
                    require_once $includes_dir . 'class-bol-promotion-methods-list-table.php';
                }
                if ( class_exists( 'Bol_Promotion_Methods_List_Table', false ) ) {
                    $list_table = new Bol_Promotion_Methods_List_Table();
                }
            }

            if ( $list_table && method_exists( $list_table, 'get_columns' ) ) {
                $table_columns = $list_table->get_columns();
                // Ensure 'cb' column for checkboxes is not included if it exists, as WP handles it.
                if ( isset( $table_columns['cb'] ) ) {
                    unset( $table_columns['cb'] );
                }
            }

            if ( !empty( $table_columns ) ) {
                // Add columns to screen options. WordPress uses this to show checkboxes.
                // The screen object should be available at this point (load-{$hook}).
                $current_screen = get_current_screen();
                if ( $current_screen ) {
                    $current_screen->add_option( 'hidden_columns', $table_columns );
                }
            }
        }

        /**
         * Renders the main settings page for the plugin.
         *
         * This method handles the display of different content based on the active tab
         * selected by the user (e.g., Dashboard, Orders, Settings). It also initializes
         * the API client for use by the various tabs.
         *
         * @return void
         */
        public function render_settings_page() {
            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
            $plugin_instance = Bol_Affiliate_Insights_Plugin::get_instance();
            $api_client = $plugin_instance->get_api_client();
            $global_selected_site_filter = get_option('bol_affiliate_insights_selected_website', 'all_sites');
            
            // Fetch available sites once for use in multiple places if needed (e.g. dashboard dropdown, tab filter display)
            $available_sites_for_dropdown = array(); 
            if ($api_client) {
                $fetched_sites = $api_client->get_available_sites(); 
                if (!empty($fetched_sites) && is_array($fetched_sites)) {
                    $available_sites_for_dropdown = $fetched_sites;
                }
            }
            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

                <h2 class="nav-tab-wrapper">
                    <a href="?page=bol-affiliate-insights&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
                    <a href="?page=bol-affiliate-insights&tab=orders" class="nav-tab <?php echo $active_tab === 'orders' ? 'nav-tab-active' : ''; ?>">Orders</a>
                    <a href="?page=bol-affiliate-insights&tab=commission_revenue" class="nav-tab <?php echo $active_tab === 'commission_revenue' ? 'nav-tab-active' : ''; ?>">Commission & Revenue</a>
                    <a href="?page=bol-affiliate-insights&tab=promotion_methods" class="nav-tab <?php echo $active_tab === 'promotion_methods' ? 'nav-tab-active' : ''; ?>">Promotion Methods</a>
                    <a href="?page=bol-affiliate-insights&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                </h2>

                <?php
                if ( $active_tab === 'dashboard' ) {
                    $current_period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'last_7_days';
                    $today_date_str = current_time('Y-m-d');
                    // Correctly initialize DateTime objects with timezone
                    $wp_timezone = wp_timezone();
                    $end_date_obj = date_create($today_date_str, $wp_timezone);
                    $start_date_obj = date_create($today_date_str, $wp_timezone);


                    if ($current_period === 'last_7_days') {
                        date_modify($end_date_obj, '-1 day'); 
                        $start_date_obj = clone $end_date_obj;
                        date_modify($start_date_obj, '-6 days'); 
                    } elseif ($current_period === 'last_30_days') {
                        date_modify($end_date_obj, '-1 day'); 
                        $start_date_obj = clone $end_date_obj;
                        date_modify($start_date_obj, '-29 days');
                    } elseif ($current_period === 'this_year') {
                        $start_date_obj = date_create(date('Y-01-01'), $wp_timezone); 
                        $end_date_obj = date_create(date('Y-12-31'), $wp_timezone);   
                    } elseif ($current_period === 'today') {
                        // Start and end date are already today
                    }
                    
                    $start_date = $start_date_obj->format('Y-m-d');
                    $end_date = $end_date_obj->format('Y-m-d');
                    ?>
                    <h3>Dashboard Metrics</h3>
                    <div class="dashboard-period-selector">
                        Time range:
                        <a href="?page=bol-affiliate-insights&tab=dashboard&period=today" class="<?php echo $current_period === 'today' ? 'current active' : ''; ?>">Today</a> |
                        <a href="?page=bol-affiliate-insights&tab=dashboard&period=last_7_days" class="<?php echo $current_period === 'last_7_days' ? 'current active' : ''; ?>">Last 7 Days</a> |
                        <a href="?page=bol-affiliate-insights&tab=dashboard&period=last_30_days" class="<?php echo $current_period === 'last_30_days' ? 'current active' : ''; ?>">Last 30 Days</a> |
                        <a href="?page=bol-affiliate-insights&tab=dashboard&period=this_year" class="<?php echo $current_period === 'this_year' ? 'current active' : ''; ?>">This Year</a>
                    </div>
                    <hr>
                    <?php
                    $total_orders = 0;
                    $total_clicks = 0;
                    $total_revenue = 0.0;
                    $total_commission = 0.0;
                    $conversion_rate = 0.0;
                    $error_messages = array();

                    if (!$api_client) {
                        $error_messages[] = "API Client could not be initialized. Check plugin configuration.";
                    } else {
                        $promo_data_response = $api_client->get_promotion_methods_report( $start_date, $end_date ); 
                        $promo_items = array();
                        if ( is_wp_error( $promo_data_response ) ) {
                            $error_messages[] = "Error fetching promotion data: " . esc_html( $promo_data_response->get_error_message() );
                        } elseif ( isset($promo_data_response['items']) ) {
                            $promo_items = $promo_data_response['items'];
                            if ($global_selected_site_filter !== 'all_sites' && !empty($promo_items)) {
                                $promo_items = array_filter($promo_items, function($item) use ($global_selected_site_filter) {
                                    return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                });
                            }
                            foreach ( $promo_items as $item ) {
                                $total_clicks += isset($item['clicks']) ? (int)$item['clicks'] : 0;
                                $total_orders += isset($item['orders']) ? (int)$item['orders'] : 0;
                                $total_revenue += isset($item['revenueInclVat']) ? (float)$item['revenueInclVat'] : 0.0;
                            }
                        } elseif (!isset($promo_data_response['items']) && !is_wp_error($promo_data_response)) {
                             $error_messages[] = "Promotion data response is not in the expected format.";
                        }

                        $orders_report_data = $api_client->get_orders_report( $start_date, $end_date );
                        if ( is_wp_error( $orders_report_data ) ) {
                            $error_messages[] = "Error fetching orders report for commission: " . esc_html( $orders_report_data->get_error_message() );
                        } elseif ( isset( $orders_report_data['items'] ) && !empty( $orders_report_data['items'] ) ) {
                            foreach ( $orders_report_data['items'] as $order_item ) {
                                $total_commission += isset( $order_item['commission'] ) ? (float)$order_item['commission'] : 0.0;
                            }
                        } elseif (!isset($orders_report_data['items']) && !is_wp_error($orders_report_data)) {
                             $error_messages[] = "Orders report data for commission is not in the expected format.";
                        }
                    }

                    if ( $total_clicks > 0 ) {
                        $conversion_rate = ( $total_orders / $total_clicks ) * 100;
                    }

                    if ( !empty($error_messages) ) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . implode( '</p><p>', $error_messages ) . '</p></div>';
                    }
                    ?>
                    <div class="metrics-container">
                        <div class="metric-box"><h4>Orders</h4><p><?php echo number_format_i18n( $total_orders ); ?></p></div>
                        <div class="metric-box"><h4>Clicks</h4><p><?php echo number_format_i18n( $total_clicks ); ?></p></div>
                        <div class="metric-box"><h4>Revenue</h4><p><?php echo '€' . number_format_i18n( $total_revenue, 2 ); ?></p></div>
                        <div class="metric-box"><h4>Commission</h4><p><?php echo '€' . number_format_i18n( $total_commission, 2 ); ?></p></div>
                        <div class="metric-box"><h4>Conversion Rate</h4><p><?php echo number_format_i18n( $conversion_rate, 2 ); ?>%</p></div>
                    </div>
                    <hr style="margin-top:30px;">
                    <div class="chart-container">
                        <h3>Performance Chart</h3>
                        <div class="chart-controls">
                            <div>
                                <label for="chart-metric-selector">Metric:</label>
                                <select id="chart-metric-selector">
                                    <option value="orders" selected>Orders</option>
                                    <option value="clicks">Clicks</option>
                                    <option value="revenue">Revenue</option>
                                    <option value="commission">Commission</option>
                                    <option value="conversion">Conversion Rate</option>
                                </select>
                            </div>
                            <div>
                                <label for="chart-period-selector">Period:</label>
                                <select id="chart-period-selector">
                                    <option value="last_4_weeks" selected>Last 4 Weeks</option>
                                    <option value="this_month">This Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="last_30_days">Last 30 Days</option>
                                    <option value="last_7_days">Last 7 Days</option>
                                    <option value="this_year">This Year</option>
                                    <option value="last_year">Last Year</option>
                                </select>
                            </div>
                            <div>
                                <label for="chart-granularity-selector">Granularity:</label>
                                <select id="chart-granularity-selector">
                                    <option value="auto" selected>Auto</option>
                                    <option value="month">Month</option>
                                    <option value="week">Week</option>
                                    <option value="day">Day</option>
                                </select>
                            </div>
                            <div>
                                <label for="chart-site-selector">Site:</label>
                                <select id="chart-site-selector">
                                    <option value="all_sites" selected>All Sites</option>
                                    <?php if ( ! empty( $available_sites_for_dropdown ) ) : ?>
                                        <?php foreach ( $available_sites_for_dropdown as $site_code => $site_name ) : ?>
                                            <option value="<?php echo esc_attr( $site_code ); ?>">
                                                <?php echo esc_html( $site_name ); ?> (<?php echo esc_html($site_code); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="button" id="bol-update-chart-button" class="button button-secondary">Update Chart</button>
                        </div>
                        <div style="max-width: 800px; margin: auto;">
                            <canvas id="bolPerformanceChart"></canvas>
                        </div>
                        <div id="bol-chart-error-message"></div>
                        <div id="bol-chart-data-table-container" style="margin-top: 20px;">
                        </div>
                    </div>
                    <?php
                } elseif ( $active_tab === 'orders' ) {
                    echo '<h3>Orders Report</h3>';
                    $current_start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date_create('now', wp_timezone())->modify('-30 days')->format('Y-m-d');
                    $current_end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : current_time('Y-m-d');
                    ?>
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="bol-affiliate-insights">
                        <input type="hidden" name="tab" value="orders">
                        <label for="orders-start-date">From:</label>
                        <input type="text" id="orders-start-date" name="start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
                        <label for="orders-end-date">To:</label>
                        <input type="text" id="orders-end-date" name="end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
                        <input type="submit" value="Fetch Orders" class="button button-secondary">
                    </form>
                    <hr>
                    <div id="orders-data-container">
                        <?php
                        echo '<h4>Orders from ' . esc_html($current_start_date) . ' to ' . esc_html($current_end_date) . '</h4>';
                        if (!$api_client) {
                            echo '<div class="notice notice-error is-dismissible"><p>API Client not available. Check plugin configuration.</p></div>';
                        } else {
                            $orders_data_response = $api_client->get_orders_report($current_start_date, $current_end_date);
                            $order_items_to_display = array();

                            if (is_wp_error($orders_data_response)) {
                                echo '<div class="notice notice-error is-dismissible"><p>Error fetching orders: ' . esc_html($orders_data_response->get_error_message()) . '</p></div>';
                            } elseif (!isset($orders_data_response['items'])) {
                                echo '<div class="notice notice-error is-dismissible"><p>Orders data response is not in the expected format.</p></div>';
                            } else {
                                $order_items_to_display = $orders_data_response['items'];
                                
                                $orders_list_table = new Bol_Orders_List_Table();
                                $orders_list_table->prepare_items( $order_items_to_display );
                                echo '<h3>Orders Data</h3>';
                                if ($global_selected_site_filter !== 'all_sites') {
                                    echo '<p><em>Note: The Orders Report itself does not provide per-item site filtering. Displaying all orders. The global site filter applies to other reports.</em></p>';
                                }
                                $orders_list_table->display();
                                if (empty($order_items_to_display)) {
                                     echo '<div class="notice notice-info is-dismissible"><p>No orders found for the selected period.</p></div>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <?php
                } elseif ( $active_tab === 'commission_revenue' ) {
                    echo '<h3>Commission & Revenue Report</h3>';
                    $default_start_date = date_create(current_time('Y') . '-01-01', wp_timezone())->format('Y-m-d');
                    $default_end_date = current_time('Y-m-d');
                    $current_start_date = isset( $_GET['cr_start_date'] ) ? sanitize_text_field( $_GET['cr_start_date'] ) : $default_start_date;
                    $current_end_date = isset( $_GET['cr_end_date'] ) ? sanitize_text_field( $_GET['cr_end_date'] ) : $default_end_date;
                    ?>
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="bol-affiliate-insights">
                        <input type="hidden" name="tab" value="commission_revenue">
                        <label for="cr-start-date">From:</label>
                        <input type="text" id="cr-start-date" name="cr_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
                        <label for="cr-end-date">To:</label>
                        <input type="text" id="cr-end-date" name="cr_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
                        <input type="submit" value="Fetch Report" class="button button-secondary">
                    </form>
                    <hr>
                    <div id="commission-revenue-data-container">
                        <?php
                        echo '<h4>Report from ' . esc_html($current_start_date) . ' to ' . esc_html($current_end_date) . '</h4>';
                        if (!$api_client) {
                            echo '<div class="notice notice-error is-dismissible"><p>API Client not available. Check plugin configuration.</p></div>';
                        } else {
                            $report_data_response = $api_client->get_commission_revenue_report($current_start_date, $current_end_date);
                            $cr_items_to_display = array();

                            if (is_wp_error($report_data_response)) {
                                echo '<div class="notice notice-error is-dismissible"><p>Error fetching report: ' . esc_html($report_data_response->get_error_message()) . '</p></div>';
                            } elseif (!isset($report_data_response['items'])) {
                                echo '<div class="notice notice-error is-dismissible"><p>Report data response is not in the expected format.</p></div>';
                            } else {
                                $cr_items_to_display = $report_data_response['items'];
                                if ($global_selected_site_filter !== 'all_sites' && !empty($cr_items_to_display)) {
                                    $cr_items_to_display = array_filter($cr_items_to_display, function($item) use ($global_selected_site_filter) {
                                        return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                    });
                                }

                                $cr_list_table = new Bol_Commission_Revenue_List_Table();
                                $cr_list_table->prepare_items( $cr_items_to_display );
                                echo '<h3>Commission & Revenue Data</h3>';
                                if ($global_selected_site_filter !== 'all_sites') {
                                    $site_name_display = $global_selected_site_filter; 
                                    if(isset($available_sites_for_dropdown[$global_selected_site_filter])) {
                                        $site_name_display = $available_sites_for_dropdown[$global_selected_site_filter] . " (" . $global_selected_site_filter . ")";
                                    }
                                    echo '<p><em>Displaying data filtered for site: ' . esc_html($site_name_display) . '</em></p>';
                                }
                                $cr_list_table->display();
                                if (empty($cr_items_to_display) && isset($report_data_response['items']) && !empty($report_data_response['items'])) { // Check if filter resulted in empty
                                     echo '<div class="notice notice-info is-dismissible"><p>No records found for the selected site and period.</p></div>';
                                } elseif (empty($cr_items_to_display)) {
                                     echo '<div class="notice notice-info is-dismissible"><p>No records found for the selected period.</p></div>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <?php
                } elseif ( $active_tab === 'promotion_methods' ) {
                    echo '<h3>Promotion Methods Report</h3>';
                    $default_start_date = date_create(current_time('Y') . '-01-01', wp_timezone())->format('Y-m-d');
                    $default_end_date = current_time('Y-m-d');
                    $current_start_date = isset( $_GET['pm_start_date'] ) ? sanitize_text_field( $_GET['pm_start_date'] ) : $default_start_date;
                    $current_end_date = isset( $_GET['pm_end_date'] ) ? sanitize_text_field( $_GET['pm_end_date'] ) : $default_end_date;
                    ?>
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="bol-affiliate-insights">
                        <input type="hidden" name="tab" value="promotion_methods">
                        <label for="pm-start-date">From:</label>
                        <input type="text" id="pm-start-date" name="pm_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
                        <label for="pm-end-date">To:</label>
                        <input type="text" id="pm-end-date" name="pm_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
                        <input type="submit" value="Fetch Report" class="button button-secondary">
                    </form>
                    <hr>
                    <div id="promotion-methods-data-container">
                        <?php
                        echo '<h4>Report from ' . esc_html($current_start_date) . ' to ' . esc_html($current_end_date) . '</h4>';
                        if (!$api_client) {
                            echo '<div class="notice notice-error is-dismissible"><p>API Client not available. Check plugin configuration.</p></div>';
                        } else {
                            $report_data_response = $api_client->get_promotion_methods_report($current_start_date, $current_end_date);
                            $pm_items_to_display = array();

                            if (is_wp_error($report_data_response)) {
                                echo '<div class="notice notice-error is-dismissible"><p>Error fetching report: ' . esc_html($report_data_response->get_error_message()) . '</p></div>';
                            } elseif (!isset($report_data_response['items'])) {
                                echo '<div class="notice notice-error is-dismissible"><p>Report data response is not in the expected format.</p></div>';
                            } else {
                                $pm_items_to_display = $report_data_response['items'];
                                if ($global_selected_site_filter !== 'all_sites' && !empty($pm_items_to_display)) {
                                    $pm_items_to_display = array_filter($pm_items_to_display, function($item) use ($global_selected_site_filter) {
                                        return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                    });
                                }
                                
                                $pm_list_table = new Bol_Promotion_Methods_List_Table();
                                $pm_list_table->prepare_items( $pm_items_to_display );
                                echo '<h3>Promotion Methods Data</h3>';
                                 if ($global_selected_site_filter !== 'all_sites') {
                                    $site_name_display = $global_selected_site_filter; 
                                    if(isset($available_sites_for_dropdown[$global_selected_site_filter])) {
                                        $site_name_display = $available_sites_for_dropdown[$global_selected_site_filter] . " (" . $global_selected_site_filter . ")";
                                    }
                                    echo '<p><em>Displaying data filtered for site: ' . esc_html($site_name_display) . '</em></p>';
                                }
                                $pm_list_table->display();
                                if (empty($pm_items_to_display) && isset($report_data_response['items']) && !empty($report_data_response['items'])) {
                                     echo '<div class="notice notice-info is-dismissible"><p>No records found for the selected site and period.</p></div>';
                                } elseif (empty($pm_items_to_display)) {
                                     echo '<div class="notice notice-info is-dismissible"><p>No records found for the selected period.</p></div>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <?php
                } elseif ( $active_tab === 'settings' ) {
                    ?>
                    <form action="options.php" method="post">
                        <?php
                        settings_fields( 'bol_affiliate_insights_options_group' ); 
                        do_settings_sections( 'bol-affiliate-insights-settings' ); 
                        submit_button( 'Save Settings' );
                        ?>
                    </form>
                    <hr/>
                    <h2>Test API Connection</h2>
                    <button type="button" id="bol-test-connection-button" class="button">Test Connection</button>
                    <div id="bol-test-connection-results"></div>
                    <hr/>
                    <h2>Getting Your API Credentials</h2>
                    <p>To obtain your Bol.com Client ID and Client Secret:</p>
                    <ol>
                        <li>Log in to your <a href="https://partner.bol.com/" target="_blank">Bol.com Partner Program account</a>.</li>
                        <li>Navigate to 'Account'.</li>
                        <li>Scroll down to the 'Open API' section.</li>
                        <li>Click 'Toevoegen' (Add) to create new credentials if you don't have them.</li>
                        <li>Enter a name for your credentials and save.</li>
                        <li>Your Client ID will be visible. Click 'Toon secret' (Show secret) to view and copy your Client Secret.</li>
                    </ol>
                    <p><strong>Important:</strong> Keep your Client Secret confidential. Do not share it.</p>
                    <?php
                } else {
                    echo '<h3>Dashboard</h3><p>Dashboard content will go here (default).</p>';
                }
                ?>
            </div>
            <?php
        }
    }
}
?>
