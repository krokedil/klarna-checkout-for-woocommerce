<?php // phpcs:ignore
/**
 * Order lines class
 *
 * @package KlarnaShippingService/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order lines class
 */
class KSS_Order_Lines {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_cart_line_item', array( $this, 'add_product_dimensions' ), 10, 2 );
		add_filter( 'kco_wc_cart_line_item', array( $this, 'add_shipping_class' ), 10, 2 );
	}

	/**
	 * Maybe add the shipping class to the Kustom item.
	 *
	 * @param array $klarna_item The Kustom Item.
	 * @param array $cart_item The Cart Item.
	 * @return array
	 */
	public function add_product_dimensions( $klarna_item, $cart_item ) {
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}

		$product_weight = ! empty( $product->get_weight() ) ? $product->get_weight() : 0;
		$product_height = ! empty( $product->get_height() ) ? $product->get_height() : 0;
		$product_width  = ! empty( $product->get_width() ) ? $product->get_width() : 0;
		$product_length = ! empty( $product->get_length() ) ? $product->get_length() : 0;

		$klarna_item['shipping_attributes']['weight']     = round( wc_get_weight( $product_weight, 'g' ) );
		$klarna_item['shipping_attributes']['dimensions'] = array(
			'height' => round( wc_get_dimension( $product_height, 'mm' ) ),
			'width'  => round( wc_get_dimension( $product_width, 'mm' ) ),
			'length' => round( wc_get_dimension( $product_length, 'mm' ) ),
		);

		return $klarna_item;
	}

	/**
	 * Maybe add the shipping class to the Kustom item.
	 *
	 * @param array $klarna_item The Kustom Item.
	 * @param array $cart_item The Cart Item.
	 * @return array
	 */
	public function add_shipping_class( $klarna_item, $cart_item ) {
		$tags = array();
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}
		$shipping_class = $product->get_shipping_class();
		if ( ! empty( $shipping_class ) ) {
			$tags[]                                     = $shipping_class;
			$klarna_item['shipping_attributes']['tags'] = $tags;
		}
		return $klarna_item;
	}
} new KSS_Order_Lines();
