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
		return array(
			'name'         => $order_item->get_name(),
			'quantity'     => $order_item->get_quantity(),
			'total_amount' => intval( $order_item->get_total() * 100 ),
			'unit_price'   => intval( $order_item->get_total() / $order_item->get_quantity() * 100 ),
		);
	}

	public function get_order_line_shipping( $order ) {
		return array(
			'name'         => $order->get_shipping_method(),
			'quantity'     => 1,
			'total_amount' => intval( $order->get_shipping_total() * 100 ),
			'unit_price'   => intval( $order->get_shipping_total() * 100 ),
		);
	}

	public function get_order_line_fees( $order_fee ) {
		return array(
			'name'         => $order_fee->get_name(),
			'quantity'     => $order_fee->get_quantity(),
			'total_amount' => intval( $order_fee->get_total() * 100 ),
			'unit_price'   => intval( $order_fee->get_total() / $order_item->get_quantity() * 100 ),
		);
	}
}
