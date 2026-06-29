<?php // phpcs:ignore
/**
 * Edit kustom order class.
 *
 * @package KlarnaShippingService/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Edit kustom order class.
 */
class KSS_Edit_Klarna_Order {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_api_request_args', array( $this, 'maybe_add_free_shipping_tag' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'remove_shipping' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'remove_shipping_callback_url' ) );
	}

	/**
	 * Maybe adds the free shipping tag.
	 *
	 * @param array $request_args The request args for Kustom Checkout.
	 * @return array
	 */
	public function maybe_add_free_shipping_tag( $request_args ) {
		// Get old tags if they exist.
		$tags = isset( $request_args['tags'] ) ? $request_args['tags'] : array();
		foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
			$coupon = new WC_Coupon( $coupon_code );
			if ( $coupon->get_free_shipping() ) {
				$tags[] = 'ksa_free_shipping';
			}
		}
		$request_args['tags'] = $tags;
		return $request_args;
	}

	/**
	 * Remove shipping from the Kustom order. Since we don't use the server side callback, Kustom adds this themselves.
	 *
	 * @param array $request_args The request args for Kustom Checkout.
	 * @return array
	 */
	public function remove_shipping( $request_args ) {
		if ( isset( $request_args['order_lines'] ) ) {
			foreach ( $request_args['order_lines'] as $key => $order_line ) {
				if ( isset( $order_line['type'] ) && 'shipping_fee' === $order_line['type'] ) {
					unset( $request_args['order_lines'][ $key ] );
					$request_args['order_amount']     = $request_args['order_amount'] - $order_line['unit_price'];
					$request_args['order_tax_amount'] = $request_args['order_tax_amount'] - $order_line['total_tax_amount'];
				}
			}
			// Reset the order line keys to prevent malformed json error.
			$request_args['order_lines'] = array_values( $request_args['order_lines'] );
		}
		return $request_args;
	}

	/**
	 * Removes the shipping callback url incase it is set.
	 *
	 * @param array $request_args The request args for Kustom Checkout.
	 * @return array
	 */
	public function remove_shipping_callback_url( $request_args ) {
		if ( isset( $request_args['merchant_urls']['shipping_option_update'] ) ) {
			unset( $request_args['merchant_urls']['shipping_option_update'] );
		}
		return $request_args;
	}
} new KSS_Edit_Klarna_Order();
