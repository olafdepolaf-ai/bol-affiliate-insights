<?php

namespace TuinenBalkon\TBMoneyManager\Bol\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PromotionMethodsListTable extends \WP_List_Table {

	private array $affiliate_link_index = array();
	private bool  $hide_site_column     = false;

	public function set_affiliate_link_index( array $index ): void {
		$this->affiliate_link_index = $index;
	}

	public function set_hide_site_column( bool $hide ): void {
		$this->hide_site_column = $hide;
	}

	public function __construct( $args = array() ) {
		parent::__construct( array(
			'singular' => 'Promotion Method',
			'plural'   => 'Promotion Methods',
			'ajax'     => false,
		) );
	}

	public function get_columns(): array {
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
			$columns = array_merge(
				array( 'date' => $columns['date'] ),
				array( 'siteName' => 'Site Name' ),
				array_slice( $columns, 1 )
			);
		}
		return $columns;
	}

	public function get_sortable_columns(): array {
		return array(
			'date'              => array( 'date', false ),
			'siteName'          => array( 'siteName', false ),
			'name'              => array( 'name', false ),
			'subId'             => array( 'subId', false ),
			'clicks'            => array( 'clicks', false ),
			'impressions'       => array( 'impressions', false ),
			'clickThroughRate'  => array( 'clickThroughRate', false ),
			'earningsPerClick'  => array( 'earningsPerClick', false ),
			'orders'            => array( 'orders', false ),
			'conversion'        => array( 'conversion', false ),
			'revenueInclVat'    => array( 'revenueInclVat', false ),
			'averageOrderValue' => array( 'averageOrderValue', false ),
		);
	}

	public function prepare_items( $data = array() ): void {
		$this->_column_headers = array( $this->get_columns(), (array) $this->get_hidden_columns(), $this->get_sortable_columns() );

		if ( empty( $_REQUEST['orderby'] ) ) {
			$_REQUEST['orderby'] = 'date';
			$_REQUEST['order']   = 'desc';
		}

		$this->items = $data;

		$orderby    = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date';
		$order      = ! empty( $_REQUEST['order'] )   ? sanitize_text_field( $_REQUEST['order'] )   : 'desc';
		$float_cols = array( 'clicks', 'impressions', 'clickThroughRate', 'earningsPerClick', 'orders', 'conversion', 'revenueInclVat', 'averageOrderValue' );

		usort( $this->items, function ( $a, $b ) use ( $orderby, $order, $float_cols ) {
			$val_a = $a[ $orderby ] ?? '';
			$val_b = $b[ $orderby ] ?? '';

			if ( in_array( $orderby, $float_cols, true ) ) {
				$val_a = floatval( str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', $val_a ) ) );
				$val_b = floatval( str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', $val_b ) ) );
			} elseif ( $orderby === 'date' ) {
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

		$per_page     = 20;
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
			case 'date':
				if ( ! empty( $item[ $column_name ] ) && is_string( $item[ $column_name ] ) ) {
					$date = date_create( $item[ $column_name ] );
					if ( $date ) return esc_html( date_format( $date, 'Y-m-d' ) );
				}
				return 'N/A';
			case 'clicks':
			case 'impressions':
			case 'orders':
				return number_format_i18n( (int) $item[ $column_name ] );
			case 'clickThroughRate':
			case 'conversion':
				return number_format_i18n( (float) preg_replace( '/[^\d,.]/', '', str_replace( ',', '.', $item[ $column_name ] ) ), 2 ) . '%';
			case 'earningsPerClick':
			case 'revenueInclVat':
			case 'averageOrderValue':
				return '€' . number_format_i18n( (float) preg_replace( '/[^\d,.]/', '', str_replace( ',', '.', $item[ $column_name ] ) ), 2 );
			case 'ta_link':
				return $this->render_ta_link_cell( $item );
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : 'N/A';
		}
	}

	private function render_ta_link_cell( array $item ): string {
		if ( empty( $this->affiliate_link_index['by_subid'] ) && empty( $this->affiliate_link_index['by_name'] ) ) {
			return '';
		}

		$subid = strtolower( trim( $item['subId'] ?? '' ) );
		$name  = strtolower( trim( $item['name'] ?? '' ) );
		$link  = null;

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

	public function no_items(): void {
		echo 'No promotion method data found for the selected period.';
	}
}
