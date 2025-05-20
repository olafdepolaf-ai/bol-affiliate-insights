<?php
/**
 * Bol_Promotion_Methods_List_Table Class
 *
 * Extends WP_List_Table to display promotion methods data from the Bol.com API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Ensure WP_List_Table is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'Bol_Promotion_Methods_List_Table' ) ) {
    /**
     * List table class for displaying Bol.com Promotion Methods data.
     *
     * This class extends WP_List_Table to generate a table display for data
     * retrieved from the Bol.com Affiliate API (Promotion Report). It defines
     * columns, handles data preparation, and formats cell content.
     */
    class Bol_Promotion_Methods_List_Table extends WP_List_Table {

        /**
         * Constructor for Bol_Promotion_Methods_List_Table.
         *
         * Sets singular and plural item names and other table arguments.
         * Calls the parent class constructor.
         *
         * @param array $args Optional. Arguments to pass to the parent constructor.
         */
        public function __construct( $args = array() ) {
            parent::__construct( array(
                'singular' => 'Promotion Method', // Singular name of the listed records.
                'plural'   => 'Promotion Methods',// Plural name of the listed records.
                'ajax'     => false              // This table does not support AJAX.
            ) );
        }

        /**
         * Defines the columns for the promotion methods table.
         *
         * These columns correspond to the data fields available in the Bol.com Promotion Report.
         *
         * @return array An associative array of column slugs => titles.
         */
        public function get_columns() {
            $columns = array(
                'date'              => 'Date',                  // Date of the record.
                'siteName'          => 'Site Name',             // Name of the affiliate site.
                'frameType'         => 'Frame Type',            // Type of frame/link used.
                'name'              => 'Link Name',             // Name of the link/promotion.
                'subId'             => 'SubID',                 // Affiliate SubID used.
                'clicks'            => 'Clicks',                // Number of clicks.
                'impressions'       => 'Impressions',           // Number of impressions (optional, can be large).
                'clickThroughRate'  => 'CTR (%)',               // Click-Through Rate.
                'earningsPerClick'  => 'EPC (€)',               // Earnings Per Click.
                // 'earningsPerMille'  => 'EPM (€)',            // Earnings Per Mille (1000 impressions) (optional).
                'orders'            => 'Orders',                // Number of orders generated.
                'conversion'        => 'Conversion (%)',        // Conversion rate.
                'revenueInclVat'    => 'Revenue (VAT Incl.)',   // Revenue including VAT.
                'averageOrderValue' => 'AOV (€)'                // Average Order Value.
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
         * @param array $data An array of promotion methods data from the API.
         * @return void
         */
        public function prepare_items( $data = array() ) {
            $columns = $this->get_columns();
            $hidden = array(); // Array of hidden columns.
            $this->_column_headers = array( $columns, $hidden, array() ); // Set column headers (no sortable columns for now).

            // If actual data is empty, use sample data for testing the table structure.
            if (empty($data)) {
                $this->items = array(
                    array('date' => '2023-01-01', 'siteName' => 'My Site', 'frameType' => 'Productlink', 'name' => 'Product XYZ', 'subId' => 'sidebar', 'clicks' => 150, 'impressions' => 10000, 'clickThroughRate' => 1.5, 'earningsPerClick' => 0.25, 'orders' => 5, 'conversion' => 3.33, 'revenueInclVat' => 250.75, 'averageOrderValue' => 50.15),
                );
            } else {
                $this->items = $data; // Assign provided data to items.
            }
            // Note: Pagination logic would go here if needed.
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
                case 'date':
                    // Assuming date format YYYY-MM-DD from API, display as is.
                    return esc_html( $item[ $column_name ] ); 
                case 'clicks':
                case 'impressions':
                case 'orders':
                    // Format integer values.
                    return number_format_i18n( (int)$item[ $column_name ] );
                case 'clickThroughRate':
                case 'conversion':
                    // Format percentage values with two decimal places.
                    return number_format_i18n( (float)$item[ $column_name ], 2 ) . '%';
                case 'earningsPerClick':
                case 'revenueInclVat':
                case 'averageOrderValue':
                    // Format currency values.
                    return '€' . number_format_i18n( (float)$item[ $column_name ], 2 );
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
            _e( 'No promotion method data found for the selected period.', 'bol-affiliate-insights' );
        }
    }
}
?>
