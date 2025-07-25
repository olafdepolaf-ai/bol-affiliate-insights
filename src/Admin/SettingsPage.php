<?php
namespace TuinenBalkon\BolAffiliateInsights\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SettingsPage {

    public function __construct() {
        // The MenuService will handle adding the menu page
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        $plugin_instance = \TuinenBalkon\BolAffiliateInsights\Plugin::get_instance();
        $api_client = $plugin_instance->get_api_client();
        $global_selected_site_filter = get_option('bol_affiliate_insights_selected_website', 'all_sites');
        
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
                    } elseif ( !isset($promo_data_response['items']) ) {
                         $error_messages[] = "Promotion data response is not in the expected format.";
                    } else {
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
                    }

                    $orders_report_data = $api_client->get_orders_report( $start_date, $end_date );
                    if ( is_wp_error( $orders_report_data ) ) {
                        $error_messages[] = "Error fetching orders report for commission: " . esc_html( $orders_report_data->get_error_message() );
                    } elseif ( !isset( $orders_report_data['items'] ) ) {
                         $error_messages[] = "Orders report data for commission is not in the expected format.";
                    } else {
                        if ( !empty( $orders_report_data['items'] ) ) {
                            foreach ( $orders_report_data['items'] as $order_item ) {
                                $total_commission += isset( $order_item['commission'] ) ? (float)$order_item['commission'] : 0.0;
                            }
                        }
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
                    <?php wp_nonce_field('bol_orders_date_range', 'bol_orders_nonce'); ?>
                    <label for="orders-start-date">From:</label>
                    <input type="text" id="orders-start-date" name="start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
                    <label for="orders-end-date">To:</label>
                    <input type="text" id="orders-end-date" name="end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
                    <input type="submit" value="Fetch Orders" class="button button-secondary">
                </form>
                <hr>
                <div id="orders-data-container">
                    <?php
                    if (isset($_GET['start_date']) && isset($_GET['bol_orders_nonce']) && wp_verify_nonce($_GET['bol_orders_nonce'], 'bol_orders_date_range')) {
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
                                
                                $orders_list_table = new \TuinenBalkon\BolAffiliateInsights\Table\OrdersListTable();
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
                    } else {
                        echo '<p>Select a date range and click \'Fetch Orders\' to view the report.</p>';
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
                    <?php wp_nonce_field('bol_cr_date_range', 'bol_cr_nonce'); ?>
                    <label for="cr-start-date">From:</label>
                    <input type="text" id="cr-start-date" name="cr_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
                    <label for="cr-end-date">To:</label>
                    <input type="text" id="cr-end-date" name="cr_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
                    <input type="submit" value="Fetch Report" class="button button-secondary">
                </form>
                <hr>
                <div id="commission-revenue-data-container">
                    <?php
                    if (isset($_GET['cr_start_date']) && isset($_GET['bol_cr_nonce']) && wp_verify_nonce($_GET['bol_cr_nonce'], 'bol_cr_date_range')) {
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

                                $cr_list_table = new \TuinenBalkon\BolAffiliateInsights\Table\CommissionRevenueListTable();
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
                    } else {
                        echo '<p>Select a date range and click \'Fetch Report\' to view the data.</p>';
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
                    <?php wp_nonce_field('bol_pm_date_range', 'bol_pm_nonce'); ?>
                    <label for="pm-start-date">From:</label>
                    <input type="text" id="pm-start-date" name="pm_start_date" class="datepicker" value="<?php echo esc_attr( $current_start_date ); ?>">
                    <label for="pm-end-date">To:</label>
                    <input type="text" id="pm-end-date" name="pm_end_date" class="datepicker" value="<?php echo esc_attr( $current_end_date ); ?>">
                    <input type="submit" value="Fetch Report" class="button button-secondary">
                </form>
                <hr>
                <div id="promotion-methods-data-container">
                    <?php
                    if (isset($_GET['pm_start_date']) && isset($_GET['bol_pm_nonce']) && wp_verify_nonce($_GET['bol_pm_nonce'], 'bol_pm_date_range')) {
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
                            }
                            else {
                                $pm_items_to_display = $report_data_response['items'];
                                if ($global_selected_site_filter !== 'all_sites' && !empty($pm_items_to_display)) {
                                    $pm_items_to_display = array_filter($pm_items_to_display, function($item) use ($global_selected_site_filter) {
                                        return isset($item['siteCode']) && $item['siteCode'] == $global_selected_site_filter;
                                    });
                                }
                                
                                $pm_list_table = new \TuinenBalkon\BolAffiliateInsights\Table\PromotionMethodsListTable();
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
                    }
                    else {
                        echo '<p>Select a date range and click \'Fetch Report\' to view the data.</p>';
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