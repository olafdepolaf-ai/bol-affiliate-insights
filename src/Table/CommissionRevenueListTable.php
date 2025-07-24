<?php
namespace TuinenBalkon\BolAffiliateInsights\Table;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Ensure WP_List_Table is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CommissionRevenueListTable extends \WP_List_Table {

    /**
     * Constructor for CommissionRevenueListTable.
     *
     * Sets singular and plural item names and other table arguments.
     * Calls the parent class constructor.
     *
     * @param array $args Optional. Arguments to pass to the parent constructor.
     */
    public function __construct( $args = array() ) {
        parent::__construct( array(
            'singular' => 'Commission Record', 
            'plural'   => 'Commission Records',
            'ajax'     => false               
        ) );
    }

    /**
     * Defines the columns for the commission and revenue table.
     *
     * @return array An associative array of column slugs => titles.
     */
    public function get_columns() {
        $columns = array(
            'orderDate'             => __('Order Date', 'bol-affiliate-insights'),                        
            'siteName'              => __('Site Name', 'bol-affiliate-insights'),                         
            'frameType'             => __('Frame Type', 'bol-affiliate-insights'),                        
            'name'                  => __('Link Name', 'bol-affiliate-insights'),                         
            'subId'                 => __('SubID', 'bol-affiliate-insights'),                             
            'commissionPercentage'  => __('Comm. %', 'bol-affiliate-insights'),                           
            'commissionOriginal'    => __('Comm. Original', 'bol-affiliate-insights'),                    
            'commissionApproved'    => __('Comm. Approved', 'bol-affiliate-insights'),                    
            'commissionOpen'        => __('Comm. Open', 'bol-affiliate-insights'),                        
            'revenueOriginalInclVat'=> __('Revenue Original (VAT Incl.)', 'bol-affiliate-insights'),    
            'revenueApprovedInclVat'=> __('Revenue Approved (VAT Incl.)', 'bol-affiliate-insights'),    
            'quantityPayable'       => __('Qty. Payable', 'bol-affiliate-insights')                       
        );
        return $columns;
    }

    /**
     * Defines sortable columns.
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'orderDate'             => array('orderDate', false),
            'siteName'              => array('siteName', false),
            'frameType'             => array('frameType', false),
            'name'                  => array('name', false),
            'subId'                 => array('subId', false),
            'commissionPercentage'  => array('commissionPercentage', false),
            'commissionOriginal'    => array('commissionOriginal', false),
            'commissionApproved'    => array('commissionApproved', false),
            'commissionOpen'        => array('commissionOpen', false),
            'revenueOriginalInclVat'=> array('revenueOriginalInclVat', false),
            'revenueApprovedInclVat'=> array('revenueApprovedInclVat', false),
            'quantityPayable'       => array('quantityPayable', false)
        );
        return $sortable_columns;
    }

    // DO NOT define get_hidden_columns() here. Let it inherit from WP_List_Table.

    /**
     * Prepares the items for display in the table.
     *
     * @param array $data An array of commission/revenue data from the API.
     * @return void
     */
    public function prepare_items( $data = array() ) {
        $columns = $this->get_columns();
        $hidden = (array) $this->get_hidden_columns(); 
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Default sort order
        if ( empty( $_REQUEST['orderby'] ) ) {
            $_REQUEST['orderby'] = 'orderDate';
            $_REQUEST['order']   = 'desc';
        }

        if (empty($data)) {
            $sample_item = array('orderDate' => '2023-01-01', 'siteName' => 'My Blog', 'frameType' => 'Tekstlink', 'name' => 'Spring Sale', 'subId' => 'promo1', 'commissionPercentage' => 8.0, 'commissionOriginal' => 10.00, 'commissionApproved' => 8.00, 'commissionOpen' => 2.00, 'revenueOriginalInclVat' => 125.00, 'revenueApprovedInclVat' => 100.00, 'quantityPayable' => 5);
            $this->items = array();
            for ($i = 1; $i <= 50; $i++) {
                $item = $sample_item;
                $item['orderDate'] = date('Y-m-d', strtotime("-$i days"));
                $item['name'] = 'Campaign ' . $i;
                $item['commissionApproved'] = 8.00 + $i; 
                $item['revenueApprovedInclVat'] = 100.00 + ($i * 10);
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

                if ( in_array( $orderby, ['commissionPercentage', 'commissionOriginal', 'commissionApproved', 'commissionOpen', 'revenueOriginalInclVat', 'revenueApprovedInclVat', 'quantityPayable'] ) ) {
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
        $per_page = $this->get_items_per_page( 'bol_commission_records_per_page', 20 ); // Allow filtering items per page
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
     * @param array  $item
     * @param string $column_name
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'orderDate':
                if ( !empty($item[ $column_name ]) && is_string($item[ $column_name ]) ) {
                    try {
                       $date = date_create($item[ $column_name ]);
                       if ($date) {
                           return esc_html( date_format($date, 'Y-m-d') );
                       }
                       return esc_html( $item[ $column_name ] ) . ' (Invalid Date Format)';
                   } catch (\Exception $e) {
                       return esc_html( $item[ $column_name ] ) . ' (Error Parsing Date)';
                   }
               }
               return 'N/A';
            case 'commissionPercentage':
                return number_format_i18n( (float) preg_replace('/[^\d,.]/', '', str_replace(',', '.', $item[ $column_name ]) ), 1 ) . '%';
            case 'commissionOriginal':
            case 'commissionApproved':
            case 'commissionOpen':
            case 'revenueOriginalInclVat':
            case 'revenueApprovedInclVat':
                return '€' . number_format_i18n( (float) preg_replace('/[^\d,.]/', '', str_replace(',', '.', $item[ $column_name ]) ), 2 );
            case 'quantityPayable':
                return number_format_i18n( (int)$item[ $column_name ] );
            case 'siteName':
            case 'frameType':
            case 'name':
            case 'subId':
                return esc_html( $item[ $column_name ] );
            default:
                return isset($item[ $column_name ]) ? esc_html($item[ $column_name ]) : 'N/A';
        }
    }

    /**
     * Message to display when no items are found.
     */
    public function no_items() {
        _e( 'No commission or revenue data found for the selected period.', 'bol-affiliate-insights' );
    }
}