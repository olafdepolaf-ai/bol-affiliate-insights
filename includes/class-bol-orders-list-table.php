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
     * Bol_Orders_List_Table Class.
     *
     * Extends WP_List_Table to display order data from the Bol.com API in a sortable
     * and paginated table within the WordPress admin area. This class handles
     * defining columns, fetching and preparing data, and rendering individual cells.
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
         * Defines the columns that will be displayed in the table.
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

        /**
         * Defines which columns are sortable by the user.
         *
         * @return array An associative array of column slugs => array(column_slug, is_default_sorted).
         */

        public function get_sortable_columns() {
            $sortable_columns = array(
                'orderDate'     => array('orderDate', false),
                'orderId'       => array('orderId', false),
                'orderItemId'   => array('orderItemId', false),
                'productTitle'  => array('productTitle', false),
                'quantity'      => array('quantity', false),
                'priceInclVat'  => array('priceInclVat', false),
                'commission'    => array('commission', false),
                'status'        => array('status', false)
            );
            return $sortable_columns;
        }

        /**
         * Prepares the items for display in the table.
         *
         * This method fetches and processes data from the API, handles sorting,
         * and sets up pagination for the table.
         */
        public function prepare_items( $data = array() ) {
            $columns = $this->get_columns();
            $hidden = array(); // Array of hidden columns.
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array( $columns, $hidden, $sortable );

            // Default sort order
            if ( empty( $_REQUEST['orderby'] ) ) {
                $_REQUEST['orderby'] = 'orderDate';
                $_REQUEST['order']   = 'desc';
            }
            
            // Assign data to items property
            if (empty($data)) {
                // Sample data for testing structure (ensure enough items to test pagination if possible)
                $sample_item = array('orderDate' => '2023-01-01 10:00:00', 'orderId' => '123', 'orderItemId' => 'A1', 'productTitle' => 'Sample Product 1', 'quantity' => 1, 'priceInclVat' => 19.99, 'commission' => 1.50, 'status' => 'Open');
                $this->items = array();
                for ($i = 1; $i <= 50; $i++) { // Create 50 sample items
                    $item = $sample_item;
                    $item['orderId'] = 123 + $i;
                    $item['orderItemId'] = 'A' . $i;
                    $item['productTitle'] = 'Sample Product ' . $i;
                    $this->items[] = $item;
                }
            } else {
                $this->items = $data;
            }

            // Sorting logic
            $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'orderDate';
            $order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';

            if ( ! empty( $orderby ) ) {
                usort( $this->items, function( $a, $b ) use ( $orderby, $order ) {
                    $val_a = $a[ $orderby ];
                    $val_b = $b[ $orderby ];

                    if ( in_array( $orderby, ['quantity', 'priceInclVat', 'commission'] ) ) {
                        $val_a = floatval( str_replace(',', '.', preg_replace('/[^\d,.]/', '', $val_a) ) );
                        $val_b = floatval( str_replace(',', '.', preg_replace('/[^\d,.]/', '', $val_b) ) );
                    } elseif ( $orderby === 'orderDate' ) {
                        $time_a = strtotime( $val_a );
                        $time_b = strtotime( $val_b );
                        if ($time_a === $time_b) return 0;
                        return ( $order === 'asc' ) ? ( $time_a < $time_b ? -1 : 1 ) : ( $time_a > $time_b ? -1 : 1 );
                    } else {
                        $val_a = strtolower( (string) $val_a );
                        $val_b = strtolower( (string) $val_b );
                    }

                    if ( $val_a == $val_b ) {
                        return 0;
                    }
                    return ( $order === 'asc' ) ? ( $val_a < $val_b ? -1 : 1 ) : ( $val_a > $val_b ? -1 : 1 );
                } );
            }

            // Pagination logic
            $per_page = 20; // Or make this a class property/constant
            $current_page = $this->get_pagenum();
            $total_items = count( $this->items );

            // Slice the data for the current page
            $this->items = array_slice( $this->items, ( ( $current_page - 1 ) * $per_page ), $per_page );

            $this->set_pagination_args( array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page )
            ) );
        }

        /**
         * Defines the default rendering for each column in the table.
         *
         * This method is called for each cell and formats the output based on the
         * column name (e.g., date formatting, currency formatting).
         */
        protected function column_default( $item, $column_name ) {
            switch ( $column_name ) {
                case 'orderDate':
                    // Format the order date string.
                    if ( !empty($item[ $column_name ]) && is_string($item[ $column_name ]) ) {
                         try {
                            // Create a DateTime object with WordPress timezone and format it.
                            $date = date_create($item[ $column_name ], wp_timezone());
                            if ($date) {
                                return esc_html( date_format($date, 'Y-m-d') );
                            }
                            return esc_html( $item[ $column_name ] ) . ' (Invalid Date Format)';
                        } catch (Exception $e) {
                            // Fallback for invalid date format from API.
                            return esc_html( $item[ $column_name ] ) . ' (Error Parsing Date)'; 
                        }
                    }
                    return 'N/A'; // Placeholder if date is missing or invalid.
                case 'priceInclVat':
                case 'commission':
                    // Format currency values.
                    return '€' . number_format_i18n( (float) preg_replace('/[^\d,.]/', '', str_replace(',', '.', $item[ $column_name ]) ), 2 );
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
