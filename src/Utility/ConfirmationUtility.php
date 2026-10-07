<?php
namespace Krokedil\KustomCheckout\Utility;

use Krokedil\KustomCheckout\Express\OrderCreator;

/**
 * Class ConfirmationUtility
 *
 * Sends the customer on to the order confirmation once Kustom has completed the purchase.
 */
class ConfirmationUtility {
	/**
	 * Redirect to the order confirmation, if the Kustom order belongs to the current customer's session.
	 *
	 * @param string $kustom_order_id The Kustom order id.
	 *
	 * @return void
	 */
	public static function redirect_from_session( $kustom_order_id ) {
		// Tied to the session so that knowing a Kustom order id is not enough to obtain its order key.
		if ( ! isset( WC()->session ) ) {
			return;
		}

		// An express purchase keeps its order id under its own key, so the iframe never reuses it.
		foreach ( array( 'kco_wc_order_id', OrderCreator::SESSION_KEY ) as $session_key ) {
			$session_kustom_order_id = (string) WC()->session->get( $session_key );
			if ( ! empty( $session_kustom_order_id ) && hash_equals( $session_kustom_order_id, (string) $kustom_order_id ) ) {
				self::redirect( $kustom_order_id );
				return;
			}
		}
	}

	/**
	 * Redirect to the confirmation of the WooCommerce order placed with the Kustom order. Does nothing if there is none.
	 *
	 * @param string $kustom_order_id The Kustom order id.
	 *
	 * @return void
	 */
	public static function redirect( $kustom_order_id ) {
		$order = kco_get_order_by_klarna_id( $kustom_order_id, '2 day ago' );
		if ( empty( $order ) ) {
			return;
		}

		// The URL carries the order key, so it must not be cached.
		nocache_headers();
		wp_safe_redirect( self::get_confirmation_url( $order, $kustom_order_id ) );
		exit;
	}

	/**
	 * Get the thank you page URL that also confirms the Kustom order, unless the order is already paid.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param string    $kustom_order_id The Kustom order id.
	 *
	 * @return string
	 */
	private static function get_confirmation_url( $order, $kustom_order_id ) {
		// A zero-amount purchase, e.g. a free trial subscription, is paid directly and has nothing to confirm.
		if ( ! empty( $order->get_date_paid() ) ) {
			return $order->get_checkout_order_received_url();
		}

		return add_query_arg(
			array(
				'kco_confirm'  => 'yes',
				'kco_order_id' => $kustom_order_id,
				'order_id'     => $order->get_id(),
				'key'          => $order->get_order_key(),
			),
			$order->get_checkout_order_received_url()
		);
	}
}
