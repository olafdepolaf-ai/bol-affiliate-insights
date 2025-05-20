<?php
/**
 * Bol_Commission_Revenue_List_Table Class
 *
 * Extends WP_List_Table to display commission and revenue data from the Bol.com API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Ensure WP_List_Table is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'Bol_Commission_Revenue_List_Table' ) ) {
    /**
     * List table class for displaying Bol.com Commission and Revenue data.
     *
     * This class extends WP_List_Table to generate a table display for commission
     * and revenue data retrieved from the Bol.com Affiliate API (Commission Report).
     * It defines columns, handles data preparation, and formats cell content.
     */
    class Bol_Commission_Revenue_List_Table extends WP_List_Table {

        /**
         * Constructor for Bol_Commission_Revenue_List_Table.
         *
         * Sets singular and plural item names and other table arguments.
         * Calls the parent class constructor.
         *
         * @param array $args Optional. Arguments to pass to the parent constructor.
         */
        public function __construct( $args = array() ) {
            parent::__construct( array(
                'singular' => 'Commission Record', // Singular name of the listed records.
                'plural'   => 'Commission Records',// Plural name of the listed records.
                'ajax'     => false               // This table does not support AJAX.
            ) );
        }

        /**
         * Defines the columns for the commission and revenue table.
         *
         * These columns correspond to the data fields available in the Bol.com Commission Report.
         *
         * @return array An associative array of column slugs => titles.
         */
        public function get_columns() {
            $columns = array(
                'orderDate'             => 'Order Date',                        // Date of the order.
                'siteName'              => 'Site Name',                         // Name of the affiliate site.
                // 'siteCode'           => 'Site Code',                        // Site code (optional, can be verbose).
                'frameType'             => 'Frame Type',                        // Type of frame/link used.
                'name'                  => 'Link Name',                         // Name of the link/promotion.
                'subId'                 => 'SubID',                             // Affiliate SubID used.
                'commissionPercentage'  => 'Comm. %',                           // Commission percentage.
                'commissionOriginal'    => 'Comm. Original',                    // Original commission amount.
                'commissionApproved'    => 'Comm. Approved',                    // Approved commission amount.
                'commissionOpen'        => 'Comm. Open',                        // Open/pending commission amount.
                'revenueOriginalInclVat'=> 'Revenue Original (VAT Incl.)',    // Original revenue including VAT.
                'revenueApprovedInclVat'=> 'Revenue Approved (VAT Incl.)',    // Approved revenue including VAT.
                // 'revenueOpenInclVat'    => 'Revenue Open (VAT Incl.)',       // Open revenue including VAT (optional).
                'quantityPayable'       => 'Qty. Payable'                       // Quantity of items for which commission is payable.
            );
            return $columns;
        }

        /**
         * Prepares the items for display in the table.
         *
         * This method takes the raw data from the API, sets up column headers,
         * and assigns the data to `$this->items`. If no data is provided,
         * it populates `$this->items` with sample data for structural testing.
         *
         * @param array $data An array of commission/revenue data from the API.
         * @return void
         */
        public function prepare_items( $data = array() ) {
            $columns = $this->get_columns();
            $hidden = array(); // Array of hidden columns.
            $this->_column_headers = array( $columns, $hidden, array() ); // No sortable for now

            if (empty($data)) {
                // Updated sample data logic as above
                $sample_item = array('orderDate' => '2023-01-01', 'siteName' => 'My Blog', 'frameType' => 'Tekstlink', 'name' => 'Spring Sale', 'subId' => 'promo1', 'commissionPercentage' => 8.0, 'commissionOriginal' => 10.00, 'commissionApproved' => 8.00, 'commissionOpen' => 2.00, 'revenueOriginalInclVat' => 125.00, 'revenueApprovedInclVat' => 100.00, 'quantityPayable' => 5);
                $this->items = array();
                for ($i = 1; $i <= 50; $i++) {
                    $item = $sample_item;
                    $item['orderDate'] = date('Y-m-d', strtotime("-$i days"));
                    $item['name'] = 'Campaign ' . $i;
                    $item['commissionApproved'] = 8.00 + $i; // Vary some data
                    $item['revenueApprovedInclVat'] = 100.00 + ($i * 10);
                    $this->items[] = $item;
                }
            } else {
                $this->items = $data;
            }

            // Pagination logic
            $per_page = 20;
            $current_page = $this->get_pagenum();
            $total_items = count( $this->items );

            $this->items = array_slice( $this->items, ( ( $current_page - 1 ) * $per_page ), $per_page );

            $this->set_pagination_args( array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page )
            ) );
        }

        /**
         * Defines the default rendering for each column.
         *
         * This method is called for each cell in the table. It formats data
         * such as dates, percentages, currency, and numbers for display.
         *
         * @param array  $item        A singular item (one row's data).
         * @param string $column_name The name/slug of the column to be displayed.
         * @return string Text or HTML to be displayed in the cell.
         */
        protected function column_default( $item, $column_name ) {
            switch ( $column_name ) {
                case 'orderDate':
                    // Assuming date format YYYY-MM-DD from API for this report, display as is.
                    return esc_html( $item[ $column_name ] ); 
                case 'commissionPercentage':
                    // Format commission percentage with one decimal place.
                    return number_format_i18n( (float)$item[ $column_name ], 1 ) . '%';
                case 'commissionOriginal':
                case 'commissionApproved':
                case 'commissionOpen':
                case 'revenueOriginalInclVat':
                case 'revenueApprovedInclVat':
                    // Format currency values.
                    return '€' . number_format_i18n( (float)$item[ $column_name ], 2 );
                case 'quantityPayable':
                    // Format quantity as an integer.
                    return number_format_i18n( (int)$item[ $column_name ] );
                case 'siteName':
                case 'frameType':
                case 'name':
                case 'subId':
                    // Escape and display text values.
                    return esc_html( $item[ $column_name ] );
                default:
                    // For debugging: show raw value if not specifically handled, or 'N/A'.
                    return isset($item[ $column_name ]) ? esc_html($item[ $column_name ]) : 'N/A';
            }
        }

        /**
         * Message to display when no items are found in the table.
         *
         * @return void
         */
        public function no_items() {
            // Localized message for when the table is empty.
            _e( 'No commission or revenue data found for the selected period.', 'bol-affiliate-insights' );
        }
    }
}
?>
