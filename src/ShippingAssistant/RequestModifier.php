<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request modifier class.
 *
 * Adjusts outgoing Kustom order requests for TMS-controlled shipping: tags free-shipping coupons, and
 * strips any WooCommerce-calculated shipping line since Kustom adds its own via the TMS.
 */
class RequestModifier {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_api_request_args', array( $this, 'maybe_add_free_shipping_tag' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'remove_shipping' ) );
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
			$coupon = new \WC_Coupon( $coupon_code );
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
		// If the session is available, see if we have any override data for the shipping option.
		if ( null !== WC()->session ) {
			$kco_order_id = WC()->session->get( 'kco_wc_order_id' );

			// If we have the override data for the shipping option, we should not remove the shipping line,
			// since we have replaced it with the updated shipping data from WooCommerce instead.
			$override_data = get_transient( "kss_override_data_$kco_order_id" );
			if ( $override_data ) {
				return $request_args;
			}
		}

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
}
