<?php
namespace Krokedil\KustomCheckout\CheckoutFlow;

use Exception;

/**
 * Class for processing the redirect checkout flow on the shortcode checkout page and pay for order pages.
 */
class RedirectFlow extends CheckoutFlow {
	/**
	 * Process the payment for the WooCommerce order.
	 *
	 * @param \WC_Order $order The WooCommerce order to be processed.
	 *
	 * @return array
	 * @throws Exception If there is an error during the payment processing.
	 */
	public function process( $order ) {
		$klarna_order = KCO_WC()->api->create_klarna_order( $order->get_id(), 'redirect' );

		if ( empty( $klarna_order ) ) {
			throw new Exception( __( "We couldn't create your payment session right now. Please try again in a moment or contact us if the issue continues.", 'klarna-checkout-for-woocommerce' ) );
		}

		$this->save_order_metadata( $order, $klarna_order, 'redirect', false );

		$hpp = KCO_WC()->api->create_klarna_hpp_url( $klarna_order['order_id'], $order->get_id() );

		if ( is_wp_error( $hpp ) ) {
			\KCO_Logger::log( \sprintf( '[Process]: Failed to create a HPP session with Kustom Order %s|%s (Kustom ID: %s).', $order->get_id(), $order->get_order_number(), $klarna_order['order_id'] ) );
			throw new Exception( __( "We couldn't start your payment session right now. Please try again in a moment or contact us if the issue continues.", 'klarna-checkout-for-woocommerce' ) );
		}

		$hpp_redirect = $hpp['redirect_url'];

		// Save Kustom HPP url & Session ID.
		$order->update_meta_data( '_wc_klarna_hpp_url', sanitize_text_field( $hpp_redirect ) );
		$order->update_meta_data( '_wc_klarna_hpp_session_id', sanitize_key( $hpp['session_id'] ) );
		$order->save();

		\KCO_Logger::log( sprintf( 'Processing order %s|%s (Kustom ID: %s) OK. Redirecting to hosted payment page.', $order->get_id(), $order->get_order_number(), $klarna_order['order_id'] ) );

		// All good. Redirect customer to Kustom Hosted payment page.
		$order->add_order_note( __( 'Customer redirected to Kustom Hosted Payment Page.', 'klarna-checkout-for-woocommerce' ) );

		return array(
			'result'   => 'success',
			'redirect' => $hpp_redirect,
		);
	}
}
