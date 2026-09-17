<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request modifier class.
 *
 * Adjusts outgoing Kustom order requests for TMS-controlled shipping: tags free-shipping coupons,
 * strips any WooCommerce-calculated shipping line since Kustom adds its own via the TMS, and puts
 * that line back when the store has overridden the TMS shipping data.
 */
class RequestModifier {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_api_request_args', array( $this, 'maybe_add_free_shipping_tag' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'remove_shipping' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'maybe_add_shipping_override_data' ), 15 ); // Needs to happen after remove shipping.
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
	 * Add shipping override data to the Kustom order as a order row, and update the order total.
	 *
	 * @param array $request_args The request args for Kustom Checkout.
	 *
	 * @return array
	 */
	public function maybe_add_shipping_override_data( $request_args ) {
		// If the session is available, see if we have any override data for the shipping option.
		if ( null === WC()->session ) {
			return $request_args;
		}

		$kco_order_id  = WC()->session->get( 'kco_wc_order_id' );
		$override_data = get_transient( "kss_override_data_$kco_order_id" );

		// If we have no override data, just return the request args as is.
		if ( ! $override_data ) {
			return $request_args;
		}

		// Use the same logic as KCO_Request_Cart for the shipping data. The price has already been set in ShippingMethod.
		if ( WC()->shipping->get_packages() && ! empty( WC()->session->get( 'chosen_shipping_methods' ) ) ) {
			$kco_cart_helper = new \KCO_Request_Cart();
			$shipping        = array(
				'type'             => 'shipping_fee',
				'reference'        => $kco_cart_helper->get_shipping_reference(),
				'name'             => $kco_cart_helper->get_shipping_name(),
				'quantity'         => 1,
				'unit_price'       => $kco_cart_helper->get_shipping_amount(),
				'tax_rate'         => $kco_cart_helper->get_shipping_tax_rate(),
				'total_amount'     => $kco_cart_helper->get_shipping_amount(),
				'total_tax_amount' => $kco_cart_helper->get_shipping_tax_amount(),
			);

			$request_args['order_lines'][] = $shipping;

			$request_args['order_amount']     += $shipping['unit_price'];
			$request_args['order_tax_amount'] += $shipping['total_tax_amount'];
		}

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
}
