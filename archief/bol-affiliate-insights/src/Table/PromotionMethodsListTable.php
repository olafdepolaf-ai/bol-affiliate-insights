<?php
namespace TuinenBalkon\BolAffiliateInsights\Table;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Ensure WP_List_Table is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PromotionMethodsListTable extends \WP_List_Table {

    /** @var array Bol params index from the affiliate adapter (by_subid, by_name). */
    private $affiliate_link_index = array();

    /** @var bool When true the siteName column is hidden (single-site view). */
    private $hide_site_column = false;

    /**
     * Sets the affiliate link index used to match promotion rows to TA links.
     * Build this with AffiliateLinkAdapterInterface::build_bol_params_index().
     */
    public function set_affiliate_link_index( array $index ): void {
        $this->affiliate_link_index = $index;
    }

    /**
     * When set to true the siteName column is omitted (single-site filter active).
     */
    public function set_hide_site_column( bool $hide ): void {
        $this->hide_site_column = $hide;
    }

    /**
     * Constructor for PromotionMethodsListTable.
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
            'date'              => 'Date',
            'name'              => 'Link Name',
            'subId'             => 'SubID',
            'ta_link'           => 'Link',
            'clicks'            => 'Clicks',
            'impressions'       => 'Impressions',
            'clickThroughRate'  => 'CTR (%)',
            'earningsPerClick'  => 'EPC (€)',
            'orders'            => 'Orders',
            'conversion'        => 'Conversion (%)',
            'revenueInclVat'    => 'Revenue (VAT Incl.)',
            'averageOrderValue' => 'AOV (€)',
        );
        if ( ! $this->hide_site_column ) {
            // Insert siteName after date.
            $columns = array_merge(
                array( 'date' => $columns['date'] ),
                array( 'siteName' => 'Site Name' ),
                array_slice( $columns, 1 )
            );
        }
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
    public function get_sortable_columns() {
        $sortable_columns = array(
            'date'              => array('date', false),
            'siteName'          => array('siteName', false),
            'name'              => array('name', false),
            'subId'             => array('subId', false),
            'clicks'            => array('clicks', false),
            'impressions'       => array('impressions', false),
            'clickThroughRate'  => array('clickThroughRate', false),
            'earningsPerClick'  => array('earningsPerClick', false),
            'orders'            => array('orders', false),
            'conversion'        => array('conversion', false),
            'revenueInclVat'    => array('revenueInclVat', false),
            'averageOrderValue' => array('averageOrderValue', false),
        );
        return $sortable_columns;
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
        $hidden = (array) $this->get_hidden_columns(); 
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Default sort order
        if ( empty( $_REQUEST['orderby'] ) ) {
            $_REQUEST['orderby'] = 'date';
            $_REQUEST['order']   = 'desc';
        }

        if (empty($data)) {
            // Updated sample data logic as above
            $sample_item = array('date' => '2023-01-01', 'siteName' => 'My Site', 'frameType' => 'Productlink', 'name' => 'Product XYZ', 'subId' => 'sidebar', 'clicks' => 150, 'impressions' => 10000, 'clickThroughRate' => 1.5, 'earningsPerClick' => 0.25, 'orders' => 5, 'conversion' => 3.33, 'revenueInclVat' => 250.75, 'averageOrderValue' => 50.15);
            $this->items = array();
            for ($i = 1; $i <= 50; $i++) {
                $item = $sample_item;
                $item['date'] = date('Y-m-d', strtotime("-$i days"));
                $item['name'] = 'Promotion ' . $i;
                $item['clicks'] = 150 + ($i * 5); // Vary some data
                $item['orders'] = 5 + $i;
                $item['revenueInclVat'] = 250.75 + ($i * 10);
                // Note: CTR, EPC, Conversion, AOV would ideally be recalculated based on varied data
                // For sample data, keeping them static from sample_item is okay for now, or set to 0.
                // To be more realistic, only keep raw data like clicks, impressions, orders, revenue.
                // For simplicity of sample data, we are not recalculating derived metrics here.
                $this->items[] = $item;
            }
        } else {
            $this->items = $data;
        }

        // Sorting logic
        $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date';
        $order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';

        if ( ! empty( $orderby ) ) {
            usort( $this->items, function( $a, $b ) use ( $orderby, $order ) {
                $val_a = $a[ $orderby ];
                $val_b = $b[ $orderby ];

                if ( in_array( $orderby, ['clicks', 'impressions', 'clickThroughRate', 'earningsPerClick', 'orders', 'conversion', 'revenueInclVat', 'averageOrderValue'] ) ) {
                    $val_a = floatval( str_replace(',', '.', preg_replace('/[^\d,.]/', '', $val_a) ) );
                    $val_b = floatval( str_replace(',', '.', preg_replace('/[^\d,.]/', '', $val_b) ) );
                } elseif ( $orderby === 'date' ) {
                    // Assuming YYYY-MM-DD format
                    $time_a = strtotime( $val_a );
                    $time_b = strtotime( $val_b );
                    if ($time_a === $time_b) return 0;
                    return ( $order === 'asc' ) ? ( $time_a < $time_b ? -1 : 1 ) : ( $time_a > $time_b ? -1 : 1 );
                } else {
                    // String comparison
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
            case 'date':
                // Assuming date format YYYY-MM-DD from API.
                if ( !empty($item[ $column_name ]) && is_string($item[ $column_name ]) ) {
                    try {
                       $date_obj = date_create($item[ $column_name ]);
                       if ($date_obj) {
                           return esc_html( date_format($date_obj, 'Y-m-d') );
                       }
                       return esc_html( $item[ $column_name ] ) . ' (Invalid Date Format)';
                   } catch (\Exception $e) {
                       return esc_html( $item[ $column_name ] ) . ' (Error Parsing Date)';
                   }
               }
               return 'N/A';
            case 'clicks':
            case 'impressions':
            case 'orders':
                // Format integer values.
                return number_format_i18n( (int)$item[ $column_name ] );
            case 'clickThroughRate':
            case 'conversion':
                // Format percentage values with two decimal places.
                return number_format_i18n( (float) preg_replace('/[^\d,.]/', '', str_replace(',', '.', $item[ $column_name ]) ), 2 ) . '%';
            case 'earningsPerClick':
            case 'revenueInclVat':
            case 'averageOrderValue':
                // Format currency values.
                return '€' . number_format_i18n( (float) preg_replace('/[^\d,.]/', '', str_replace(',', '.', $item[ $column_name ]) ), 2 );
            case 'siteName':
            case 'name':
            case 'subId':
                return esc_html( $item[ $column_name ] );
            case 'ta_link':
                return $this->render_ta_link_cell( $item );
            default:
                return isset($item[ $column_name ]) ? esc_html($item[ $column_name ]) : 'N/A';
        }
    }

    /**
     * Message to display when no items are found in the table.
     *
     * @return void
     */
    /**
     * Renders the ThirstyAffiliates link cell for a promotion methods row.
     * Matches on subId first (most specific), falls back to name.
     * Only shows icons when a matching bol.com affiliate link is found.
     */
    private function render_ta_link_cell( array $item ): string {
        if ( empty( $this->affiliate_link_index['by_subid'] ) && empty( $this->affiliate_link_index['by_name'] ) ) {
            return '';
        }

        $subid = strtolower( trim( $item['subId'] ?? '' ) );
        $name  = strtolower( trim( $item['name'] ?? '' ) );

        $link = null;
        if ( $subid !== '' && isset( $this->affiliate_link_index['by_subid'][ $subid ] ) ) {
            $link = $this->affiliate_link_index['by_subid'][ $subid ];
        } elseif ( $name !== '' && isset( $this->affiliate_link_index['by_name'][ $name ] ) ) {
            $link = $this->affiliate_link_index['by_name'][ $name ];
        }

        if ( ! $link ) {
            return '';
        }

        $edit_url   = esc_url( admin_url( 'post.php?post=' . (int) $link['id'] . '&action=edit' ) );
        $target_url = esc_url( $link['redirect_url'] ?: $link['url'] );
        $link_name  = esc_attr( $link['name'] );

        return '<a href="' . $target_url . '" target="_blank" rel="noopener" title="Bekijk: ' . $link_name . '">[&rarr;]</a>'
             . '&nbsp;<a href="' . $edit_url . '" title="Bewerk in ThirstyAffiliates: ' . $link_name . '">[&#9998;]</a>';
    }

    public function no_items() {
        // Localized message for when the table is empty.
        _e( 'No promotion method data found for the selected period.', 'bol-affiliate-insights' );
    }
}