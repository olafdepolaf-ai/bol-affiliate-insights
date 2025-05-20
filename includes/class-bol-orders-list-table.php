<?php
/**
 * Bol_Orders_List_Table Class
 *
 * Extends WP_List_Table to display order data from the Bol.com API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Ensure WP_List_Table is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'Bol_Orders_List_Table' ) ) {
    /**
     * List table class for displaying Bol.com Orders.
     *
     * This class extends WP_List_Table to generate a table display for order data
     * retrieved from the Bol.com Affiliate API. It defines columns, handles data
     * preparation, and formats cell content for display.
     */
    class Bol_Orders_List_Table extends WP_List_Table {

        /**
         * Constructor for Bol_Orders_List_Table.
         *
         * Sets singular and plural item names and other table arguments.
         * Calls the parent class constructor.
         *
         * @param array $args Optional. Arguments to pass to the parent constructor.
         */
        public function __construct( $args = array() ) {
            parent::__construct( array(
                'singular' => 'Order',      // Singular name of the listed records.
                'plural'   => 'Orders',     // Plural name of the listed records.
                'ajax'     => false         // This table does not support AJAX.
            ) );
        }

        /**
         * Defines the columns for the orders table.
         *
         * These columns correspond to the data fields available in the Bol.com Order Report.
         *
         * @return array An associative array of column slugs => titles.
         */
        public function get_columns() {
            $columns = array(
                // 'cb'            => '<input type="checkbox" />', // Checkbox for bulk actions (optional).
                'orderDate'     => 'Order Date',        // Date and time of the order.
                'orderId'       => 'Order ID',          // Unique identifier for the order.
                'orderItemId'   => 'Order Item ID',     // Unique identifier for the item within the order.
                'productTitle'  => 'Product Title',     // Title of the product ordered.
                'quantity'      => 'Quantity',          // Number of units of the product ordered.
                'priceInclVat'  => 'Price (VAT Incl.)', // Price per unit, including VAT.
                'commission'    => 'Commission',        // Commission earned for this order item.
                'status'        => 'Status'             // Status of the order item (e.g., Open, Geaccepteerd).
            );
            return $columns;
        }

        // Define sortable columns (optional, but good for user experience)
        // For now, let's make a few sortable. API might not support sorting, so this would be client-side after fetching all.
        // Or, we might need to implement server-side sorting via API params if possible.
        // For this step, let's assume client-side sorting or no sorting initially to keep it simple.
        // public function get_sortable_columns() {
        //     $sortable_columns = array(
        //         'orderDate'  => array('orderDate', false), //true for already sorted
        //         'priceInclVat' => array('priceInclVat', false),
        //         'commission' => array('commission', false)
        //     );
        //     return $sortable_columns;
        // }

        /**
         * Prepares the items for display in the table.
         *
         * This method takes the raw data from the API, sets up column headers,
         * and assigns the data to `$this->items`. If no data is provided,
         * it populates `$this->items` with sample data for structural testing.
         * Pagination logic is currently commented out, assuming all relevant data is passed.
         *
         * @param array $data An array of order data from the API.
         * @return void
         */
        public function prepare_items( $data = array() ) {
            $columns = $this->get_columns();
            $hidden = array(); // Array of hidden columns.
            // $sortable = $this->get_sortable_columns(); // If using sortable columns.
            $this->_column_headers = array( $columns, $hidden, array() /*$sortable*/ ); // Set column headers.
            
            // Process data for display.
            // If actual data is empty, use sample data for testing the table structure.
            if (empty($data)) {
                $this->items = array(
                    array('orderDate' => '2023-01-01 10:00:00', 'orderId' => '123', 'orderItemId' => 'A1', 'productTitle' => 'Sample Product 1', 'quantity' => 1, 'priceInclVat' => 19.99, 'commission' => 1.50, 'status' => 'Open'),
                    array('orderDate' => '2023-01-02 11:00:00', 'orderId' => '124', 'orderItemId' => 'B2', 'productTitle' => 'Another Thing', 'quantity' => 2, 'priceInclVat' => 9.99, 'commission' => 0.75, 'status' => 'Geaccepteerd'),
                );
            } else {
                $this->items = $data; // Assign provided data to items.
            }

            // Pagination logic (currently commented out).
            // Assumes all items for the selected date range are fetched and passed.
            // If pagination is needed, this section would slice the array and set pagination args.
            // $per_page = 20;
            // $current_page = $this->get_pagenum();
            // $total_items = count( $this->items );
            // $this->items = array_slice( $this->items, ( ( $current_page - 1 ) * $per_page ), $per_page );
            // $this->set_pagination_args( array(
            //     'total_items' => $total_items,
            //     'per_page'    => $per_page 
            // ) );
        }

        /**
         * Defines the default rendering for each column.
         *
         * This method is called for each cell in the table. It formats data
         * such as dates, currency, and numbers for display.
         *
         * @param array  $item        A singular item (one row's data).
         * @param string $column_name The name/slug of the column to be displayed.
         * @return string Text or HTML to be displayed in the cell.
         */
        protected function column_default( $item, $column_name ) {
            switch ( $column_name ) {
                case 'orderDate':
                    // Format the order date string.
                    if ( !empty($item[ $column_name ]) && is_string($item[ $column_name ]) ) {
                         try {
                            // Create a DateTime object with WordPress timezone and format it.
                            return esc_html( date_format(date_create($item[ $column_name ], wp_timezone()), 'Y-m-d H:i:s') );
                        } catch (Exception $e) {
                            // Fallback for invalid date format from API.
                            return esc_html( $item[ $column_name ] ) . ' (Invalid Date)'; 
                        }
                    }
                    return 'N/A'; // Placeholder if date is missing or invalid.
                case 'priceInclVat':
                case 'commission':
                    // Format currency values.
                    return '€' . number_format_i18n( (float)$item[ $column_name ], 2 );
                case 'quantity':
                    // Format quantity as an integer.
                    return number_format_i18n( (int)$item[ $column_name ] );
                case 'orderId':
                case 'orderItemId':
                case 'productTitle':
                case 'status':
                    // Escape and display text values.
                    return esc_html( $item[ $column_name ] );
                default:
                    // For debugging: show the whole item array for unhandled columns.
                    return print_r( $item, true ); 
            }
        }

        // Optional: For specific column formatting, create methods like:
        // function column_productTitle( $item ) {
        //     // Example: Make product title bold.
        //     return '<strong>' . esc_html( $item['productTitle'] ) . '</strong>';
        // }

        // Optional: For 'cb' column (checkboxes)
        // protected function column_cb($item) {
        //     // Example: Render a checkbox for each item.
        //     return sprintf(
        //         '<input type="checkbox" name="order[]" value="%s" />', $item['orderItemId'] // Assuming orderItemId is a unique ID.
        //     );
        // }

        /**
         * Message to display when no items are found in the table.
         *
         * @return void
         */
        public function no_items() {
            // Localized message for when the table is empty.
            _e( 'No orders found for the selected period.', 'bol-affiliate-insights' );
        }
    }
}
?>
