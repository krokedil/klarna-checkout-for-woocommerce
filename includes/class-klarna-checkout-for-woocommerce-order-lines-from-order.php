<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Klarna_Checkout_For_WooCommerce_Order_Lines_From_Order {
	public function get_order_amount( $order_id ) {
		$order = wc_get_order( $order_id );
		return intval( $order->get_total() * 100 );
	}

	public function get_total_tax( $order_id ) {
		$order = wc_get_order( $order_id );
		return intval( $order->get_total_tax( $order_id ) * 100 );
	}

	public function get_order_line_items( $order_item ) {
		$order_id = $order_item->get_order_id();
		$order    = wc_get_order( $order_id );
		return array(
			'name'             => $order_item->get_name(),
			'quantity'         => $order_item->get_quantity(),
			'total_amount'     => intval( ( $order_item->get_total() + $order_item->get_total_tax() ) * 100 ),
			'unit_price'       => intval( ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity() * 100 ),
			'total_tax_amount' => intval( $order_item->get_total_tax() * 100 ),
			'tax_rate'         => $this->get_order_line_tax_rate( $order ),
		);
	}

	public function get_order_line_shipping( $order ) {
		return array(
			'name'             => $order->get_shipping_method(),
			'quantity'         => 1,
			'total_amount'     => intval( ( $order->get_shipping_total() + $order->get_shipping_tax() ) * 100 ),
			'unit_price'       => intval( ( $order->get_shipping_total() + $order->get_shipping_tax() ) * 100 ),
			'total_tax_amount' => intval( $order->get_shipping_tax() * 100 ),
			'tax_rate'         => ( '0' !== $order->get_shipping_tax() ) ? $this->get_order_line_tax_rate( $order ) : 0,
		);
	}

	public function get_order_line_fees( $order_fee ) {
		$order_id = $order_fee->get_order_id();
		$order    = wc_get_order( $order_id );
		return array(
			'name'             => $order_fee->get_name(),
			'quantity'         => $order_fee->get_quantity(),
			'total_amount'     => intval( ( $order_fee->get_total() + $order_fee->get_total_tax() ) * 100 ),
			'unit_price'       => intval( ( $order_fee->get_total() + $order_fee->get_total_tax() ) / $order_item->get_quantity() * 100 ),
			'total_tax_amount' => intval( $order_fee->get_total_tax() * 100 ),
			'tax_rate'         => ( '0' !== $order->get_total_tax() ) ? $this->get_order_line_tax_rate( $order ) : 0,
		);
	}

	public function get_order_line_tax_rate( $order ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			return intval( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] * 100 );
		}
	}
}
