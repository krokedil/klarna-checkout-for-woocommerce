<?php
/**
 * Gets the order information from an order.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for processing order lines from a WooCommerce order.
 */
class KCO_Order_Lines_From_Order {
	/**
	 * Gets the order amount
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return int
	 */
	public function get_order_amount( $order_id ) {
		$order = wc_get_order( $order_id );
		return round( $order->get_total() * 100 );
	}

	/**
	 * Get the order total tax.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return int
	 */
	public function get_total_tax( $order_id ) {
		$order = wc_get_order( $order_id );
		return round( $order->get_total_tax( $order_id ) * 100 );
	}

	/**
	 * Gets the formated order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order line item.
	 * @return array
	 */
	public function get_order_line_items( $order_item ) {
		$order_id = $order_item->get_order_id();
		$order    = wc_get_order( $order_id );
		return array(
			'name'             => $order_item->get_name(),
			'quantity'         => $order_item->get_quantity(),
			'total_amount'     => round( ( $order_item->get_total() + $order_item->get_total_tax() ) * 100 ),
			'unit_price'       => round( ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity() * 100 ),
			'total_tax_amount' => round( $order_item->get_total_tax() * 100 ),
			'tax_rate'         => $this->get_order_line_tax_rate( $order, $order_item ),
		);
	}

	/**
	 * Gets the formated order line shipping.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array
	 */
	public function get_order_line_shipping( $order ) {
		return array(
			'name'             => $order->get_shipping_method(),
			'quantity'         => 1,
			'total_amount'     => round( ( $order->get_shipping_total() + $order->get_shipping_tax() ) * 100 ),
			'unit_price'       => round( ( $order->get_shipping_total() + $order->get_shipping_tax() ) * 100 ),
			'total_tax_amount' => round( $order->get_shipping_tax() * 100 ),
			'tax_rate'         => ( '0' !== $order->get_shipping_tax() ) ? $this->get_order_line_tax_rate( $order, current( $order->get_items( 'shipping' ) ) ) : 0,
		);
	}

	/**
	 * Gets the formated order line fees.
	 *
	 * @param WC_Order_Item_Fee $order_fee The order item fee.
	 * @return array
	 */
	public function get_order_line_fees( $order_fee ) {
		$order_id = $order_fee->get_order_id();
		$order    = wc_get_order( $order_id );
		return array(
			'name'             => $order_fee->get_name(),
			'quantity'         => $order_fee->get_quantity(),
			'total_amount'     => round( ( $order_fee->get_total() + $order_fee->get_total_tax() ) * 100 ),
			'unit_price'       => round( ( $order_fee->get_total() + $order_fee->get_total_tax() ) * 100 ),
			'total_tax_amount' => round( $order_fee->get_total_tax() * 100 ),
			'tax_rate'         => ( '0' !== $order->get_total_tax() ) ? $this->get_order_line_tax_rate( $order, current( $order->get_items( 'fee' ) ) ) : 0,
		);
	}

	/**
	 * Gets the order line tax rate.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param mixed    $order_item If not false the WooCommerce order item WC_Order_Item.
	 * @return int
	 */
	public function get_order_line_tax_rate( $order, $order_item = false ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			foreach ( $order_item->get_taxes()['total'] as $key => $value ) {
				if ( '' !== $value ) {
					if ( $rate_id === $key ) {
						return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] * 100 );
					}
				}
			}
		}
	}
}
