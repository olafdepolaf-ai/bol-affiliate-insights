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
            check_ajax_referer( 'bol_chart_data_nonce', 'nonce' ); // Verify the nonce for security.

            // Get parameters from AJAX request.
            $metric = isset( $_POST['metric'] ) ? sanitize_key( $_POST['metric'] ) : 'orders';
            $period = isset( $_POST['period'] ) ? sanitize_key( $_POST['period'] ) : 'last_4_weeks'; // Default, will be used by date range calculation
            $granularity = isset( $_POST['granularity'] ) ? sanitize_key( $_POST['granularity'] ) : 'auto';
            $chart_specific_site_filter = isset( $_POST['site'] ) ? sanitize_key( $_POST['site'] ) : 'all_sites';

            // Get global site filter
            $global_selected_site = get_option('bol_affiliate_insights_selected_website', 'all_sites');

            // Determine effective site filter: global takes precedence
            $effective_site_filter = $global_selected_site !== 'all_sites' ? $global_selected_site : $chart_specific_site_filter;

            // Dynamic dataset label based on metric
            $dataset_label = ucfirst( str_replace( '_', ' ', $metric ) );
            if ($metric === 'conversion') { // Specific case for 'conversion_rate' often displayed as 'Conversion'
                $dataset_label = 'Conversion Rate';
            }


            // Initialize data structure for chart
            $chart_data = array(
                'labels' => array(),
                'datasets' => array(
                    array(
                        'label' => $dataset_label,
                        'data' => array(),
                        'backgroundColor' => 'rgba(0, 115, 170, 0.5)',
                        'borderColor' => 'rgba(0, 115, 170, 1)',
                        'borderWidth' => 1,
                        'tension' => 0.1 // For line charts if we use them
                    )
                )
            );
            $error_message = null;
            $api_data_items = array(); // To store items from API calls

            // API Client
            $api_client = Bol_Affiliate_Insights_Plugin::get_instance()->get_api_client();
            if (!$api_client) {
                wp_send_json_error(array('message' => 'API Client not available. Please configure API credentials.'));
                return;
            }

            // Core logic for date range, granularity, labels, data fetching & aggregation will go here.
            // This will replace the placeholder logic below.

            // START: New Date Range, Granularity, Label, and Data Aggregation Logic
            $start_date_obj = new DateTimeImmutable('now', wp_timezone()); // Default to today
            $end_date_obj = new DateTimeImmutable('now', wp_timezone());   // Default to today
            $effective_granularity = $granularity; // Will be adjusted if 'auto' or overridden

            try {
                // Date Range Calculation
                switch ($period) {
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
                        // Bol API typically uses end date as yesterday if 'today' is the reference for 'last X days'
                        $end_date_obj = new DateTimeImmutable('yesterday', wp_timezone());
                        $start_date_obj = $end_date_obj->modify('-29 days'); // 29 days prior to yesterday = 30 days total
                        break;
                    case 'last_7_days':
                        $end_date_obj = new DateTimeImmutable('yesterday', wp_timezone());
                        $start_date_obj = $end_date_obj->modify('-6 days'); // 6 days prior to yesterday = 7 days total
                        break;
                    case 'today':
                        // $start_date_obj and $end_date_obj are already today by default
                        break;
                    case 'entire_period': // Fallback for 'entire_period' - defaults to 'this_year'
                    default: // Default to 'this_year' or a sensible range if period is unknown
                        $start_date_obj = new DateTimeImmutable(date('Y-01-01'), wp_timezone());
                        $end_date_obj = new DateTimeImmutable(date('Y-12-31'), wp_timezone());
                        $period = 'this_year'; // Ensure period variable reflects the change
                        break;
                }
            } catch (Exception $e) {
                $error_message = 'Error calculating date range: ' . $e->getMessage();
                wp_send_json_error(array('message' => $error_message));
                return;
            }

            // Effective Granularity Calculation
            $diff_days = $end_date_obj->diff($start_date_obj)->days;

            if ($granularity === 'auto') {
                if ($period === 'this_year' || $period === 'last_year') {
                    $effective_granularity = 'month';
                } elseif ($diff_days <= 42) {
                    $effective_granularity = 'day';
                } elseif ($diff_days <= 365) {
                    $effective_granularity = 'week';
                } else {
                    $effective_granularity = 'month';
                }
            } else { // Explicit granularity selected, apply validation and overrides
                if ($granularity === 'day' && $diff_days > 90) {
                    $effective_granularity = 'week'; // Override 'day' to 'week' if range is too long
                } elseif ($granularity === 'week' && $diff_days > 730) { // Approx 2 years
                    $effective_granularity = 'month'; // Override 'week' to 'month' if range is too long
                }
                // No override needed if conditions are not met, explicit granularity stands
            }

            // Initialize labels and data array
            $chart_data['labels'] = array();
            $chart_data['datasets'][0]['data'] = array();
            $date_format_label = ''; // Not currently used, but kept for potential future use
            $week_periods_for_lookup = array(); // For robust week lookups

            // Label Generation
            $current_loop_date = clone $start_date_obj; // Use a clone for manipulation

            switch ($effective_granularity) {
                case 'month':
                    $interval = new DateInterval('P1M');
                    $period_start_iterate_month = $current_loop_date->modify('first day of this month');
                    // Adjust end_date_obj to be the first day of the month AFTER the actual end date's month to ensure full inclusion in DatePeriod
                    $period_end_iterate = (clone $end_date_obj)->modify('first day of this month next month'); 
                    
                    $date_period_iterator = new DatePeriod($period_start_iterate_month, $interval, $period_end_iterate);
                    $year_of_start = $start_date_obj->format('Y');
                    $year_of_end = $end_date_obj->format('Y');

                    foreach ($date_period_iterator as $date_point) {
                        if ($year_of_start === $year_of_end) {
                            $chart_data['labels'][] = $date_point->format('M'); // e.g., "Jan"
                        } else {
                            $chart_data['labels'][] = $date_point->format('M Y'); // e.g., "Jan 2023"
                        }
                    }
                    break;
                case 'week':
                    $interval = new DateInterval('P1W');
                    $period_start_iterate_week = $current_loop_date->modify('monday this week');
                    // Adjust end_date_obj to ensure the period covers the last week.
                    // We want to go to the Monday *after* the $end_date_obj's week ends.
                    $period_end_iterate = (clone $end_date_obj)->modify('monday next week');

                    $date_period_iterator = new DatePeriod($period_start_iterate_week, $interval, $period_end_iterate);
                    
                    foreach ($date_period_iterator as $date_point) {
                        $week_start_dt = clone $date_point; // This is Monday 00:00:00
                        $week_end_dt = (clone $date_point)->modify('+6 days')->setTime(23,59,59); // Sunday 23:59:59

                        // Ensure the generated week period does not exceed the overall $start_date_obj and $end_date_obj
                        $actual_week_start_dt = ($week_start_dt < $start_date_obj) ? $start_date_obj->setTime(0,0,0) : $week_start_dt;
                        $actual_week_end_dt = ($week_end_dt > $end_date_obj) ? $end_date_obj->setTime(23,59,59) : $week_end_dt;
                        
                        // Only add the week if it's within the original date range (handles partial weeks at start/end)
                        if ($actual_week_start_dt <= $actual_week_end_dt) {
                            $week_start_label = $actual_week_start_dt->format('M d');
                            $week_end_label = $actual_week_end_dt->format('M d Y');
                            $label_str = $week_start_label . ' - ' . $week_end_label;
                            $chart_data['labels'][] = $label_str; 
                            $week_periods_for_lookup[] = array(
                                'start_dt' => $actual_week_start_dt, 
                                'end_dt' => $actual_week_end_dt 
                            );
                        }
                    }
                    break;
                case 'day':
                    $interval = new DateInterval('P1D');
                    $period_end_iterate = (clone $end_date_obj)->modify('+1 day'); // Include the end day
                    $date_period_iterator = new DatePeriod($current_loop_date, $interval, $period_end_iterate);
                    $show_year = ($start_date_obj->format('Y') !== $end_date_obj->format('Y'));

                    foreach ($date_period_iterator as $date_point) {
                        if ($show_year) {
                             $chart_data['labels'][] = $date_point->format('M d Y'); // e.g., "Jan 01 2023"
                        } else {
                             $chart_data['labels'][] = $date_point->format('M d'); // e.g., "Jan 01"
                        }
                    }
                    break;
            }
            
            // Pre-fill data with zeros
            if (!empty($chart_data['labels'])) {
                $chart_data['datasets'][0]['data'] = array_fill(0, count($chart_data['labels']), 0);
            }
            
            // API Data Fetching and Aggregation
            $start_date_str = $start_date_obj->format('Y-m-d');
            $end_date_str = $end_date_obj->format('Y-m-d');
            $raw_api_items = array();

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
                    // Apply site filtering for promotion methods report
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
                wp_send_json_error(array('message' => $error_message));
                return;
            }
            
            // Data Aggregation
            if (!empty($raw_api_items) && !empty($chart_data['labels'])) {
                foreach ($raw_api_items as $item) {
                    try {
                        $item_date_str = '';
                        if ($metric === 'commission' && isset($item['dateTimeOrder'])) {
                            $item_date_str = $item['dateTimeOrder'];
                        } elseif (in_array($metric, array('orders', 'clicks', 'revenue', 'conversion')) && isset($item['eventDate'])) {
                            $item_date_str = $item['eventDate'];
                        }

                        if (empty($item_date_str)) continue;

                        $item_datetime = new DateTimeImmutable($item_date_str, wp_timezone());

                        $label_index = -1;

                        switch ($effective_granularity) {
                            case 'month':
                                $item_month_year_label = $item_datetime->format('M Y');
                                $item_month_label = $item_datetime->format('M');
                                // Check if labels are "M" or "M Y"
                                if (in_array($item_month_year_label, $chart_data['labels'])) {
                                    $label_index = array_search($item_month_year_label, $chart_data['labels']);
                                } elseif (in_array($item_month_label, $chart_data['labels'])) {
                                     $label_index = array_search($item_month_label, $chart_data['labels']);
                                }
                                break;
                            case 'week':
                                // Find which week label the item_datetime falls into using $week_periods_for_lookup
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
                            if ($metric === 'commission' && isset($item['commission'])) {
                                $chart_data['datasets'][0]['data'][$label_index] += (float)$item['commission'];
                            } elseif ($metric === 'orders' && isset($item['orders'])) {
                                $chart_data['datasets'][0]['data'][$label_index] += (int)$item['orders'];
                            } elseif ($metric === 'clicks' && isset($item['clicks'])) {
                                $chart_data['datasets'][0]['data'][$label_index] += (int)$item['clicks'];
                            } elseif ($metric === 'revenue' && isset($item['revenueInclVat'])) {
                                $chart_data['datasets'][0]['data'][$label_index] += (float)$item['revenueInclVat'];
                            } elseif ($metric === 'conversion') {
                                // For conversion, we need to aggregate orders and clicks separately first, then calculate.
                                // This will be handled after this loop. For now, we can store intermediate sums if needed.
                                // Or, fetch orders and clicks into $chart_data['datasets'][0]['data'] and $chart_data['datasets'][1]['data'] respectively.
                                // For simplicity, this part will be handled in a separate step for conversion.
                            }
                        }
                    } catch (Exception $e) {
                        // Log or handle date parsing errors for individual items if necessary
                        // error_log('Could not parse date for item: ' . print_r($item, true) . ' Error: ' . $e->getMessage());
                        continue; 
                    }
                }
                
                // Special handling for 'conversion' metric
                if ($metric === 'conversion') {
                    // We need total orders and total clicks for each period label.
                    // The current loop sums one metric. A different approach is needed for conversion.
                    // Option 1: Re-fetch or re-process for clicks and orders.
                    // Option 2: During the loop, if metric is conversion, store clicks and orders in temp arrays.
                    // Let's assume for now that the JS will make separate requests for orders and clicks
                    // if it needs to calculate conversion, or this PHP should return both datasets.
                    // For this PHP script, if $metric is 'conversion', it should calculate it.
                    // This requires having both orders and clicks data. The current structure fetches one or the other.
                    // This part needs further refinement based on how conversion is supposed to be derived.
                    // For now, if metric is conversion, data array will be empty.
                    // A proper solution would be to fetch both 'orders' and 'clicks' if metric is 'conversion'.
                    // This is a significant change to the data fetching logic.
                    // Let's leave it as is for now, and it can be a follow-up improvement.
                    // The requirement stated "calculating it after aggregating orders and clicks for each period".
                    // This means we need both.
                    // To simplify for now: if metric is 'conversion', we will return empty data.
                    // A more robust solution would involve fetching both orders and clicks data if metric is 'conversion'.
                     if ($metric === 'conversion') {
                        // This section is for the 'conversion' metric.
                        // It requires 'orders' and 'clicks' from the 'promotion_methods_report'.
                        // The $raw_api_items should already contain these if $metric was 'conversion'.
                        $aggregated_orders = array_fill(0, count($chart_data['labels']), 0);
                        $aggregated_clicks = array_fill(0, count($chart_data['labels']), 0);

                        foreach ($raw_api_items as $item) { // Items are from promotion_methods_report
                             try {
                                $item_date_str = isset($item['eventDate']) ? $item['eventDate'] : '';
                                if (empty($item_date_str)) continue;
                                $item_datetime = new DateTimeImmutable($item_date_str, wp_timezone());
                                $label_index = -1;

                                // Determine label_index (same logic as above)
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
                                // error_log('Error processing item for conversion: ' . print_r($item, true) . ' Error: ' . $e->getMessage());
                                continue;
                            }
                        }

                        // Calculate conversion rate
                        for ($i = 0; $i < count($chart_data['labels']); $i++) {
                            if ($aggregated_clicks[$i] > 0) {
                                $chart_data['datasets'][0]['data'][$i] = round(($aggregated_orders[$i] / $aggregated_clicks[$i]) * 100, 2);
                            } else {
                                $chart_data['datasets'][0]['data'][$i] = 0; // Avoid division by zero
                            }
                        }
                        $error_message = null; // Clear placeholder error message if conversion logic ran.
                    }
                }

            } elseif (empty($raw_api_items) && !$error_message) {
                // No data from API, labels are generated, data should be all zeros (already initialized as per pre-fill)
                // This is a valid state, e.g. no sales in a period.
            }


            // END: New Date Range, Granularity, Label, and Data Aggregation Logic


            // Fallback for now - if new logic isn't complete, send empty success to avoid breaking chart JS
            // if (empty($chart_data['labels']) && !$error_message) { 
            //      $chart_data['labels'] = array('Finalizing...'); 
            //      $chart_data['datasets'][0]['data'] = array(0);
            // }

            // If after all processing, labels are empty (e.g. date range resulted in no periods for the granularity)
            // and no actual error message was set, provide a generic message.
            if (empty($chart_data['labels']) && !$error_message) {
                 $chart_data['labels'] = array('No data points for the selected period and granularity.');
                 $chart_data['datasets'][0]['data'] = array(0);
                 // Optionally, set a notice if this state is unexpected.
                 // $chart_data['notice'] = 'No data points could be generated for the chart.';
            }

            // Final check for error messages before sending response
            if ($error_message) {
                // If $error_message was set by conversion placeholder, but conversion is now handled,
                // it might have been cleared. If it's some other error, send it.
                // The conversion logic now clears $error_message if it runs.
                wp_send_json_error( array( 'message' => $error_message ) );
            } else {
                wp_send_json_success( $chart_data );
            }
            // Minor comment for republishing purposes - 2025-05-21 07:36:05 UTC
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
            check_ajax_referer( 'bol_test_connection_nonce', 'nonce' ); // Verify the nonce for security.

            // Instantiate the Auth Service. This is crucial for getting an access token.
            if ( ! class_exists( 'Bol_API_Auth_Service' ) ) {
                wp_send_json_error( array( 'message' => 'ERROR: Authentication service class not found.' ), 500 );
                return; // Important to exit after sending JSON.
            }
            $auth_service = new Bol_API_Auth_Service();
            $token_data = $auth_service->get_access_token(); // Attempt to get the access token.

            // Check the result of getting the token.
            if ( is_wp_error( $token_data ) ) {
                // If WP_Error, send the error message back to the client.
                wp_send_json_error( array(
                    'message' => 'Connection Failed: ' . $token_data->get_error_message(),
                    'code' => $token_data->get_error_code()
                ) );
            } elseif ( $token_data ) {
                // If token data is truthy (i.e., token string was returned).
                wp_send_json_success( array( 'message' => 'Connection Successful! Access token obtained.' ) );
            } else {
                // If token data is false or other non-WP_Error falsy value.
                wp_send_json_error( array( 'message' => 'Connection Failed: Unknown error retrieving access token.' ) );
            }
            // wp_send_json_* functions include wp_die().
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
            // Only load scripts on our plugin's settings page.
            if ( $hook_suffix !== $this->settings_page_hook_suffix ) {
                return;
            }

            // Enqueue Chart.js from CDN for displaying charts.
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(), // No dependencies for Chart.js itself.
                '3.9.1', // Version number.
                true     // Load in footer.
            );

            // Enqueue WordPress jQuery UI Datepicker for date range selection.
            wp_enqueue_script('jquery-ui-datepicker');
            // Enqueue default WordPress jQuery UI styles.
            wp_enqueue_style('jquery-ui-style', admin_url('/css/jquery-ui-fresh.min.css'));

            // Enqueue custom admin styles for the plugin.
            wp_enqueue_style(
                'bol-admin-styles',
                plugins_url( '../assets/css/admin-styles.css', __FILE__ ),
                array(), // No dependencies for this stylesheet.
                '0.1.0'  // Plugin version or file version.
            );

            // Enqueue custom admin JavaScript for handling AJAX, chart updates, etc.
            wp_enqueue_script(
                'bol-admin-settings-js',
                plugins_url( '../assets/js/admin-settings.js', __FILE__ ),
                array( 'jquery', 'chart-js', 'jquery-ui-datepicker' ), // Dependencies.
                '0.1.0', // Plugin version or file version.
                true     // Load in footer.
            );

            // Localize script to pass PHP variables (like nonces) to JavaScript.
            wp_localize_script( 'bol-admin-settings-js', 'bol_settings_params', array(
                'nonce' => wp_create_nonce( 'bol_test_connection_nonce' ), // Nonce for testing API connection.
                'chart_nonce' => wp_create_nonce( 'bol_chart_data_nonce' ), // Nonce for fetching chart data.
                'fetch_sites_nonce' => wp_create_nonce( 'bol_fetch_sites_nonce' ) // Nonce for fetching sites
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
            $options_group_name = 'bol_affiliate_insights_options_group'; // Group name for settings_fields().
            $option_name = 'bol_affiliate_insights_credentials'; // Name of the option in wp_options table.

            // Register the setting.
            register_setting(
                $options_group_name,
                $option_name,
                array( $this, 'sanitize_credentials' ) // Sanitization callback.
            );

            // Register the setting for selected website.
            register_setting(
                $options_group_name, // Same group name as credentials
                'bol_affiliate_insights_selected_website',
                'sanitize_text_field' // Standard sanitization for a site code/ID.
            );

            // Add a settings section for API credentials.
            add_settings_section(
                'bol_api_credentials_section', // ID of the section.
                'API Credentials',             // Title of the section.
                array( $this, 'render_api_credentials_section_text' ), // Callback to render section description.
                'bol-affiliate-insights-settings' // Page slug for this section. Changed to be more specific.
            );

            // Add settings field for Client ID.
            add_settings_field(
                'bol_client_id',                  // ID of the field.
                'Client ID',                      // Title of the field.
                array( $this, 'render_client_id_field' ), // Callback to render the field.
                'bol-affiliate-insights-settings',         // Page slug.
                'bol_api_credentials_section',    // Section ID.
                array( 'label_for' => 'bol_client_id_field' ) // Associates label with input field.
            );

            // Add settings field for Client Secret.
            add_settings_field(
                'bol_client_secret',              // ID of the field.
                'Client Secret',                  // Title of the field.
                array( $this, 'render_client_secret_field' ), // Callback to render the field.
                'bol-affiliate-insights-settings',         // Page slug.
                'bol_api_credentials_section',    // Section ID.
                array( 'label_for' => 'bol_client_secret_field' ) // Associates label with input field.
            );

            // Add a settings section for Data Filters.
            add_settings_section(
                'bol_data_filters_section',
                'Data Filters',
                array( $this, 'render_data_filters_section_text' ),
                'bol-affiliate-insights-settings' // Same page slug
            );

            // Add settings field for Website Selector.
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
            $sites = array(); // Default to empty array

            if ($api_client) {
                $fetched_sites = $api_client->get_available_sites();
                // Ensure $fetched_sites is an array and not WP_Error before using
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
            } elseif (empty($sites) && $api_client && is_array($fetched_sites)) { // Check if $fetched_sites was an empty array from a successful call
                 echo "<option value='' disabled>No individual sites found.</option>";
            }
            // If sites could not be fetched at all (e.g. API client error), the error message above is shown.
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
            // Sanitize Client ID.
            $sanitized_input['client_id'] = isset( $input['client_id'] ) ? sanitize_text_field( $input['client_id'] ) : '';
            // Sanitize Client Secret.
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
            // Add top-level menu page.
            $this->settings_page_hook_suffix = add_menu_page(
                'Bol Affiliate Insights',                           // Page title (appears in browser tab).
                'Bol Insights',                                     // Menu title (appears in sidebar).
                'manage_options',                                   // Capability required to access.
                'bol-affiliate-insights',                           // Menu slug (unique identifier).
                array( $this, 'render_settings_page' ),            // Callback function to render page content.
                plugins_url( '../assets/images/bol-logo.png', BOL_AFFILIATE_INSIGHTS_FILE ), // Icon URL.
                25                                                  // Position in menu order.
            );
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
            // Determine the active tab from the URL, default to 'dashboard'.
            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

            // Initialize API client once for all tabs, if needed.
            $plugin_instance = Bol_Affiliate_Insights_Plugin::get_instance();
            $api_client = $plugin_instance->get_api_client();
            
            // Get the globally selected site filter
            $global_selected_site_filter = get_option('bol_affiliate_insights_selected_website', 'all_sites');
            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

                <!-- Tab Navigation -->
                <h2 class="nav-tab-wrapper">
                    <a href="?page=bol-affiliate-insights&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
                    <a href="?page=bol-affiliate-insights&tab=orders" class="nav-tab <?php echo $active_tab === 'orders' ? 'nav-tab-active' : ''; ?>">Orders</a>
                    <a href="?page=bol-affiliate-insights&tab=commission_revenue" class="nav-tab <?php echo $active_tab === 'commission_revenue' ? 'nav-tab-active' : ''; ?>">Commission & Revenue</a>
                    <a href="?page=bol-affiliate-insights&tab=promotion_methods" class="nav-tab <?php echo $active_tab === 'promotion_methods' ? 'nav-tab-active' : ''; ?>">Promotion Methods</a>
                    <a href="?page=bol-affiliate-insights&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                </h2>

                <?php
                // Display content based on the active tab.
                if ( $active_tab === 'dashboard' ) {
                    // --- Dashboard Tab Content ---
                    // Determine Selected Period & Calculate Dates for metrics.
                    $current_period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'last_7_days';

                    $today_date_str = current_time('Y-m-d');
                    $end_date_obj = date_create($today_date_str, wp_timezone());
                    $start_date_obj = date_create($today_date_str, wp_timezone());

                    if ($current_period === 'last_7_days') {
                        $end_date_obj->modify('-1 day'); // End date is yesterday
                        $start_date_obj = clone $end_date_obj;
                        $start_date_obj->modify('-6 days'); 
                    } elseif ($current_period === 'last_30_days') {
                        $end_date_obj->modify('-1 day'); // End date is yesterday
                        $start_date_obj = clone $end_date_obj;
                        $start_date_obj->modify('-29 days');
                    } elseif ($current_period === 'this_year') {
                        $start_date_obj = date_create(date('Y-01-01'), wp_timezone()); // First day of current year
                        $end_date_obj = date_create(date('Y-12-31'), wp_timezone());   // Last day of current year
                    }
                    // For 'today', $start_date_obj and $end_date_obj are already set to today.

                    $start_date = $start_date_obj->format('Y-m-d');
                    $end_date = $end_date_obj->format('Y-m-d');

                    // Display Period Selector UI
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

                    // Initialize Metrics & Fetch/Process Data
                    $total_orders = 0;
                    $total_clicks = 0;
                    $total_revenue = 0.0;
                    $total_commission = 0.0;
                    $conversion_rate = 0.0;
                    $error_messages = array();

                    // $api_client is already initialized above
                    if (!$api_client) {
                        $error_messages[] = "API Client could not be initialized. Check plugin configuration.";
                    } else {
                        // Fetch Promotion Report Data for metrics.
                        $promo_data_response = $api_client->get_promotion_methods_report( $start_date, $end_date ); // API call
                        $promo_items = array();
                        if ( is_wp_error( $promo_data_response ) ) {
                            $error_messages[] = "Error fetching promotion data: " . esc_html( $promo_data_response->get_error_message() );
                        } elseif ( isset($promo_data_response['items']) ) {
                            $promo_items = $promo_data_response['items'];
                            // Apply global site filter if set
                            if ($global_selected_site_filter !== 'all_sites' && !empty($promo_items)) {
                                $promo_items = array_filter($promo_items, function($item) use ($global_selected_site_filter) {
                                    return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                });
                            }
                            // Aggregate clicks, orders, and revenue from (filtered) promotion data.
                            foreach ( $promo_items as $item ) {
                                $total_clicks += isset($item['clicks']) ? (int)$item['clicks'] : 0;
                                $total_orders += isset($item['orders']) ? (int)$item['orders'] : 0;
                                $total_revenue += isset($item['revenueInclVat']) ? (float)$item['revenueInclVat'] : 0.0;
                            }
                        } elseif (!isset($promo_data_response['items']) && !is_wp_error($promo_data_response)) {
                             $error_messages[] = "Promotion data response is not in the expected format.";
                        }

                        // Fetch Orders Report Data for commission.
                        $orders_report_data = $api_client->get_orders_report( $start_date, $end_date );
                        if ( is_wp_error( $orders_report_data ) ) {
                            $error_messages[] = "Error fetching orders report for commission: " . esc_html( $orders_report_data->get_error_message() );
                        } elseif ( isset( $orders_report_data['items'] ) && !empty( $orders_report_data['items'] ) ) {
                            foreach ( $orders_report_data['items'] as $order_item ) {
                                // The global site filter is NOT applied here as order items don't have siteCode.
                                $total_commission += isset( $order_item['commission'] ) ? (float)$order_item['commission'] : 0.0;
                            }
                        } elseif (!isset($orders_report_data['items']) && !is_wp_error($orders_report_data)) {
                             $error_messages[] = "Orders report data for commission is not in the expected format.";
                        }
                        // If $orders_report_data['items'] is empty, $total_commission remains 0.0, which is correct.
                    }

                // Calculate conversion rate if there are clicks to avoid division by zero.
                    if ( $total_clicks > 0 ) {
                        $conversion_rate = ( $total_orders / $total_clicks ) * 100;
                    }

                // Display any accumulated error messages.
                    if ( !empty($error_messages) ) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . implode( '</p><p>', $error_messages ) . '</p></div>';
                    }

                // Display the calculated metrics in their respective boxes.
                    ?>
                    <div class="metrics-container" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
                        <div class="metric-box" style="border: 1px solid #ccc; padding: 15px; min-width: 150px; text-align: center;"><h4>Orders</h4><p style="font-size: 1.5em; margin: 5px 0;"><?php echo number_format_i18n( $total_orders ); ?></p></div>
                        <div class="metric-box" style="border: 1px solid #ccc; padding: 15px; min-width: 150px; text-align: center;"><h4>Clicks</h4><p style="font-size: 1.5em; margin: 5px 0;"><?php echo number_format_i18n( $total_clicks ); ?></p></div>
                        <div class="metric-box" style="border: 1px solid #ccc; padding: 15px; min-width: 150px; text-align: center;"><h4>Revenue</h4><p style="font-size: 1.5em; margin: 5px 0;"><?php echo '€' . number_format_i18n( $total_revenue, 2 ); ?></p></div>
                        <div class="metric-box" style="border: 1px solid #ccc; padding: 15px; min-width: 150px; text-align: center;"><h4>Commission</h4><p style="font-size: 1.5em; margin: 5px 0;"><?php echo '€' . number_format_i18n( $total_commission, 2 ); ?></p></div>
                        <div class="metric-box" style="border: 1px solid #ccc; padding: 15px; min-width: 150px; text-align: center;"><h4>Conversion Rate</h4><p style="font-size: 1.5em; margin: 5px 0;"><?php echo number_format_i18n( $conversion_rate, 2 ); ?>%</p></div>
                    </div>
                    <hr style="margin-top:30px;">
                    <?php
                    // Fetch available sites for the dropdown
                    $available_sites_for_dropdown = array(); 
                    if ($api_client) {
                        $fetched_sites = $api_client->get_available_sites(); 
                        // get_available_sites() returns an array, empty if error or no sites.
                        if (!empty($fetched_sites)) {
                            $available_sites_for_dropdown = $fetched_sites;
                        }
                        // If $api_client->get_available_sites() could return WP_Error, handle it:
                        // $result = $api_client->get_available_sites();
                        // if (is_wp_error($result)) { $error_messages[] = "Could not retrieve site list: " . esc_html($result->get_error_message()); } 
                        // else { $available_sites_for_dropdown = $result; }
                    }
                    ?>
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
                                    <option value="this_year">This Year</option>
                                    <option value="last_year">Last Year</option>
                                    <option value="entire_period">Entire Period</option>
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
                            <!-- Data table will be rendered here by JavaScript -->
                        </div>
                    </div>
                    <?php
                } elseif ( $active_tab === 'orders' ) {
                    // Inside 'orders' tab
                    echo '<h3>Orders Report</h3>';

                    // Get current date values or defaults
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

                        // $api_client is already initialized above
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
                                // Apply global site filter if set
                                if ($global_selected_site_filter !== 'all_sites' && !empty($order_items_to_display)) {
                                    $order_items_to_display = array_filter($order_items_to_display, function($item) use ($global_selected_site_filter) {
                                        // Assuming 'siteCode' is present in order items; this needs verification
                                        // If not present, orders report might not be filterable this way client-side
                                        return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                    });
                                }
                                
                                $orders_list_table = new Bol_Orders_List_Table();
                                $orders_list_table->prepare_items( $order_items_to_display );
                                echo '<h3>Orders Data</h3>';
                                if ($global_selected_site_filter !== 'all_sites') {
                                    $site_name = $global_selected_site_filter; // Ideally fetch site name for display
                                    echo '<p><em>Displaying data filtered for site: ' . esc_html($site_name) . '</em></p>';
                                }
                                $orders_list_table->display();
                                if (empty($order_items_to_display) && !empty($orders_data_response['items'])) {
                                     echo '<div class="notice notice-info is-dismissible"><p>No orders found for the selected site and period.</p></div>';
                                } elseif (empty($order_items_to_display)) {
                                     echo '<div class="notice notice-info is-dismissible"><p>No orders found for the selected period.</p></div>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <?php
                } elseif ( $active_tab === 'commission_revenue' ) {
                    // Inside 'commission_revenue' tab
                    echo '<h3>Commission & Revenue Report</h3>';

                    // Default dates: Jan 1st of current year to today
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
                        
                        // $api_client is already initialized at the top of render_settings_page()
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
                                // Apply global site filter if set
                                if ($global_selected_site_filter !== 'all_sites' && !empty($cr_items_to_display)) {
                                    $cr_items_to_display = array_filter($cr_items_to_display, function($item) use ($global_selected_site_filter) {
                                        return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                    });
                                }

                                $cr_list_table = new Bol_Commission_Revenue_List_Table();
                                $cr_list_table->prepare_items( $cr_items_to_display );
                                echo '<h3>Commission & Revenue Data</h3>';
                                if ($global_selected_site_filter !== 'all_sites') {
                                    $site_name = $global_selected_site_filter; // Ideally fetch site name
                                    echo '<p><em>Displaying data filtered for site: ' . esc_html($site_name) . '</em></p>';
                                }
                                $cr_list_table->display();
                                if (empty($cr_items_to_display) && !empty($report_data_response['items'])) {
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
                    // Inside 'promotion_methods' tab
                    echo '<h3>Promotion Methods Report</h3>';

                    // Default dates: Jan 1st of current year to today
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
                        
                        // $api_client is already initialized at the top of render_settings_page()
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
                                // Apply global site filter if set
                                if ($global_selected_site_filter !== 'all_sites' && !empty($pm_items_to_display)) {
                                    $pm_items_to_display = array_filter($pm_items_to_display, function($item) use ($global_selected_site_filter) {
                                        return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                    });
                                }
                                
                                $pm_list_table = new Bol_Promotion_Methods_List_Table();
                                $pm_list_table->prepare_items( $pm_items_to_display );
                                echo '<h3>Promotion Methods Data</h3>';
                                 if ($global_selected_site_filter !== 'all_sites') {
                                    $site_name = $global_selected_site_filter; // Ideally fetch site name
                                    echo '<p><em>Displaying data filtered for site: ' . esc_html($site_name) . '</em></p>';
                                }
                                $pm_list_table->display();
                                if (empty($pm_items_to_display) && !empty($report_data_response['items'])) {
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
                        // Use the specific page slug for settings sections related to credentials
                        settings_fields( 'bol_affiliate_insights_options_group' ); // This group is for all options.
                        do_settings_sections( 'bol-affiliate-insights-settings' ); // Page slug for these sections.
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
                    // Default to dashboard if tab is unknown
                    echo '<h3>Dashboard</h3><p>Dashboard content will go here (default).</p>';
                }
                ?>
            </div>
            <?php
        }
    }
}
?>
