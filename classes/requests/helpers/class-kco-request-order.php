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
class KCO_Request_Order {

	/**
	 * Send sales tax as separate item (US merchants).
	 *
	 * @var bool
	 */
	public $separate_sales_tax = false;

	/**
	 * Total tax of order.
	 *
	 * @var int
	 */
	public $total_tax = 0;

	/**
	 * Gets the order lines for the order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_order_lines( $order_id ) {
		$order       = wc_get_order( $order_id );
		$order_lines = array();

		foreach ( $order->get_items() as $item ) {
			array_push( $order_lines, $this->get_order_line_items( $item ) );
		}
		foreach ( $order->get_fees() as $fee ) {
			array_push( $order_lines, $this->get_order_line_fees( $fee ) );
		}
		if ( ! empty( $order->get_shipping_method() ) ) {
			array_push( $order_lines, $this->get_order_line_shipping( $order ) );
		}

		return $order_lines;
	}

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
	 * Get total tax of order.
	 *
	 * @return int
	 */
	public function get_total_tax() {
		return round( $this->total_tax );
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
			'total_amount'     => $this->get_item_total_amount( $order, $order_item ),
			'unit_price'       => $this->get_item_unit_price( $order, $order_item ),
			'total_tax_amount' => $this->get_item_total_tax_amount( $order, $order_item ),
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
			'total_amount'     => $this->get_shipping_total_amount( $order ),
			'unit_price'       => $this->get_shipping_total_amount( $order ),
			'total_tax_amount' => $this->get_shipping_total_tax_amount( $order ),
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
			'name'             => substr( $order_fee->get_name(), 0, 254 ),
			'quantity'         => $order_fee->get_quantity(),
			'total_amount'     => $this->get_fee_total_amount( $order, $order_fee ),
			'unit_price'       => $this->get_fee_unit_price( $order, $order_fee ),
			'total_tax_amount' => $this->get_fee_total_tax_amount( $order, $order_fee ),
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

	/**
	 * Get item total amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_item Order item.
	 * @return int
	 */
	public function get_item_total_amount( $order, $order_item = false ) {
		if ( $this->separate_sales_tax ) {
			$item_total_amount     = number_format( ( $order_item->get_total() ) * ( 1 + ( $this->get_order_line_tax_rate( $order, $order_item ) / 10000 ) ), wc_get_price_decimals(), '.', '' ) * 100;
			$max_order_line_amount = ( number_format( ( $order_item->get_total() + $order_item->get_total_tax() ) * 100, wc_get_price_decimals(), '.', '' ) * $order_item->get_quantity() ) * 100;
		} else {
			$item_total_amount     = ( number_format( $order_item->get_total(), wc_get_price_decimals(), '.', '' ) + number_format( $order_item->get_total_tax(), wc_get_price_decimals(), '.', '' ) ) * 100;
			$max_order_line_amount = ( number_format( ( $order_item->get_total() + $order_item->get_total_tax() ) * 100, wc_get_price_decimals(), '.', '' ) * $order_item->get_quantity() ) * 100;
		}
		// Check so the line_total isn't greater than product price x quantity.
		// This can happen when having price display set to 0 decimals.
		if ( $item_total_amount > $max_order_line_amount ) {
			$item_total_amount = $max_order_line_amount;
		}
		return round( $item_total_amount );
	}

	/**
	 * Get item unit price.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_item Order item.
	 * @return int
	 */
	public function get_item_unit_price( $order, $order_item = false ) {
		if ( $this->separate_sales_tax ) {
			$item_subtotal = $order_item->get_total() / $order_item->get_quantity();
		} else {
			$item_subtotal = ( ( number_format( $order_item->get_total(), wc_get_price_decimals(), '.', '' ) + number_format( $order_item->get_total_tax(), wc_get_price_decimals(), '.', '' ) ) / $order_item->get_quantity() ) * 100;
		}
		return round( $item_subtotal );
	}

	/**
	 * Get item total tax amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_item Order item.
	 * @return int
	 */
	public function get_item_total_tax_amount( $order, $order_item = false ) {
		if ( $this->separate_sales_tax ) {
			$item_tax_amount = 0;
		} else {
			$item_total_amount       = $this->get_item_total_amount( $order, $order_item );
			$item_total_exluding_tax = $item_total_amount / ( 1 + ( $this->get_order_line_tax_rate( $order, $order_item ) / 10000 ) );
			$item_tax_amount         = $item_total_amount - $item_total_exluding_tax;
		}
		$this->total_tax += round( $item_tax_amount );
		return round( $item_tax_amount );
	}

	/**
	 * Get shipping total amount.
	 *
	 * @param WC_Order $order WC order.
	 * @return int
	 */
	public function get_shipping_total_amount( $order ) {
		if ( $this->separate_sales_tax ) {
			$shipping_amount = (int) number_format( $order->get_shipping_total() * 100, 0, '', '' );
		} else {
			$shipping_amount = number_format( $order->get_shipping_total() + $order->get_shipping_tax(), wc_get_price_decimals(), '.', '' ) * 100;
		}
		return $shipping_amount;
	}

	/**
	 * Get shipping total tax amount.
	 *
	 * @param WC_Order $order WC order.
	 * @return int
	 */
	public function get_shipping_total_tax_amount( $order ) {
		if ( $this->separate_sales_tax ) {
			$shipping_tax_amount = 0;
		} else {
			$shipping_total_amount       = $this->get_shipping_total_amount( $order );
			$shipping_tax_rate           = ( '0' !== $order->get_shipping_tax() ) ? $this->get_order_line_tax_rate( $order, current( $order->get_items( 'shipping' ) ) ) : 0;
			$shipping_total_exluding_tax = $shipping_total_amount / ( 1 + ( $shipping_tax_rate / 10000 ) );
			$shipping_tax_amount         = $shipping_total_amount - $shipping_total_exluding_tax;
		}
		$this->total_tax += round( $shipping_tax_amount );
		return round( $shipping_tax_amount );
	}

	/**
	 * Get fee total amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_fee Order fee.
	 * @return int
	 */
	public function get_fee_total_amount( $order, $order_fee ) {
		if ( $this->separate_sales_tax ) {
			$fee_total_amount      = number_format( ( $order_fee->get_total() ) * ( 1 + ( $this->get_order_line_tax_rate( $order, $order_fee ) / 10000 ) ), wc_get_price_decimals(), '.', '' ) * 100;
			$max_order_line_amount = ( number_format( ( $order_fee->get_total() + $order_fee->get_total_tax() ) * 100, wc_get_price_decimals(), '.', '' ) * $order_fee->get_quantity() ) * 100;
		} else {
			$fee_total_amount      = number_format( ( $order_fee->get_total() ) * ( 1 + ( $this->get_order_line_tax_rate( $order, $order_fee ) / 10000 ) ), wc_get_price_decimals(), '.', '' ) * 100;
			$max_order_line_amount = ( number_format( ( $order_fee->get_total() + $order_fee->get_total_tax() ) * 100, wc_get_price_decimals(), '.', '' ) * $order_fee->get_quantity() ) * 100;
		}
		// Check so the line_total isn't greater than product price x quantity.
		// This can happen when having price display set to 0 decimals.
		if ( $fee_total_amount > $max_order_line_amount ) {
			$fee_total_amount = $max_order_line_amount;
		}
		return round( $fee_total_amount );
	}

	/**
	 * Get fee unit price.
	 *
	 * @param boolean|object $order_fee Order fee.
	 * @return int
	 */
	public function get_fee_unit_price( $order_fee ) {
		if ( $this->separate_sales_tax ) {
			$fee_subtotal = $order_fee->get_total() / $order_fee->get_quantity();
		} else {
			$fee_subtotal = ( $order_fee->get_total() + $order_fee->get_total_tax() ) / $order_fee->get_quantity();
		}
		$fee_price = number_format( $fee_subtotal, wc_get_price_decimals(), '.', '' ) * 100;
		return round( $fee_price );
	}

	/**
	 * Get fee total tax amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_fee Order fee.
	 * @return int
	 */
	public function get_fee_total_tax_amount( $order, $order_fee ) {
		if ( $this->separate_sales_tax ) {
			$fee_tax_amount = 0;
		} else {
			$fee_total_amount       = $this->get_fee_total_amount( $order, $order_fee );
			$fee_tax_rate           = ( '0' !== $order->get_total_tax() ) ? $this->get_order_line_tax_rate( $order, current( $order->get_items( 'fee' ) ) ) : 0;
			$fee_total_exluding_tax = $fee_total_amount / ( 1 + ( $fee_tax_rate / 10000 ) );
			$fee_tax_amount         = $fee_total_amount - $fee_total_exluding_tax;
		}
		$this->total_tax += round( $fee_tax_amount );
		return round( $fee_tax_amount );
	}
}
