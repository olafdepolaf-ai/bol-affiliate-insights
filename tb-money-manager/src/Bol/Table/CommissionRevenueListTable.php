<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CommissionRevenueListTable extends \WP_List_Table {

	public function __construct( $args = array() ) {
		parent::__construct( array(
			'singular' => 'Commission Record',
			'plural'   => 'Commission Records',
			'ajax'     => false,
		) );
	}

	public function get_columns(): array {
		return array(
			'orderDate'              => 'Order Date',
			'siteName'               => 'Site Name',
			'frameType'              => 'Frame Type',
			'name'                   => 'Link Name',
			'subId'                  => 'SubID',
			'commissionPercentage'   => 'Comm. %',
			'commissionOriginal'     => 'Comm. Original',
			'commissionApproved'     => 'Comm. Approved',
			'commissionOpen'         => 'Comm. Open',
			'revenueOriginalInclVat' => 'Revenue Original (VAT Incl.)',
			'revenueApprovedInclVat' => 'Revenue Approved (VAT Incl.)',
			'quantityPayable'        => 'Qty. Payable',
		);
	}

	public function get_sortable_columns(): array {
		$cols = array_keys( $this->get_columns() );
		return array_combine( $cols, array_map( fn( $c ) => array( $c, false ), $cols ) );
	}

	public function prepare_items( $data = array() ): void {
		$this->_column_headers = array( $this->get_columns(), (array) $this->get_hidden_columns(), $this->get_sortable_columns() );

		if ( empty( $_REQUEST['orderby'] ) ) {
			$_REQUEST['orderby'] = 'orderDate';
			$_REQUEST['order']   = 'desc';
		}

		$this->items = $data;

		$orderby    = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'orderDate';
		$order      = ! empty( $_REQUEST['order'] )   ? sanitize_text_field( $_REQUEST['order'] )   : 'desc';
		$float_cols = array( 'commissionPercentage', 'commissionOriginal', 'commissionApproved', 'commissionOpen', 'revenueOriginalInclVat', 'revenueApprovedInclVat', 'quantityPayable' );

		usort( $this->items, function ( $a, $b ) use ( $orderby, $order, $float_cols ) {
			$val_a = $a[ $orderby ] ?? '';
			$val_b = $b[ $orderby ] ?? '';

			if ( in_array( $orderby, $float_cols, true ) ) {
				$val_a = floatval( str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', $val_a ) ) );
				$val_b = floatval( str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', $val_b ) ) );
			} elseif ( $orderby === 'orderDate' ) {
				$time_a = strtotime( $val_a );
				$time_b = strtotime( $val_b );
				if ( $time_a === $time_b ) return 0;
				return ( $order === 'asc' ) ? ( $time_a < $time_b ? -1 : 1 ) : ( $time_a > $time_b ? -1 : 1 );
			} else {
				$val_a = strtolower( (string) $val_a );
				$val_b = strtolower( (string) $val_b );
			}

			if ( $val_a == $val_b ) return 0;
			return ( $order === 'asc' ) ? ( $val_a < $val_b ? -1 : 1 ) : ( $val_a > $val_b ? -1 : 1 );
		} );

		$per_page     = $this->get_items_per_page( 'bol_commission_records_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items  = count( $this->items );
		$this->items  = array_slice( $this->items, ( $current_page - 1 ) * $per_page, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'orderDate':
				if ( ! empty( $item[ $column_name ] ) && is_string( $item[ $column_name ] ) ) {
					$date = date_create( $item[ $column_name ] );
					if ( $date ) return esc_html( date_format( $date, 'Y-m-d' ) );
				}
				return 'N/A';
			case 'commissionPercentage':
				return number_format_i18n( (float) preg_replace( '/[^\d,.]/', '', str_replace( ',', '.', $item[ $column_name ] ) ), 1 ) . '%';
			case 'commissionOriginal':
			case 'commissionApproved':
			case 'commissionOpen':
			case 'revenueOriginalInclVat':
			case 'revenueApprovedInclVat':
				return '€' . number_format_i18n( (float) preg_replace( '/[^\d,.]/', '', str_replace( ',', '.', $item[ $column_name ] ) ), 2 );
			case 'quantityPayable':
				return number_format_i18n( (int) $item[ $column_name ] );
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : 'N/A';
		}
	}

	public function no_items(): void {
		echo 'No commission or revenue data found for the selected period.';
	}
}
