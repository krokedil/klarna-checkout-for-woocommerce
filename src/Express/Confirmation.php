<?php
namespace Krokedil\KustomCheckout\Express;

use Krokedil\KustomCheckout\Utility\ConfirmationUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Confirmation class.
 *
 * The express confirmation URL. A front-end request, not REST, since WooCommerce loads no shopper session on REST.
 */
class Confirmation {
	/**
	 * The query var carrying the Kustom order id.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'kco-express-confirm';

	/**
	 * Send the shopper on from the express confirmation URL, if this is one. Never to the checkout page.
	 */
	public static function maybe_confirm() {
		$kustom_order_id = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Kustom's redirect carries no nonce.
		// Kustom order ids are UUIDs; anything else is not worth a query, a log line or a session.
		if ( ! preg_match( '/^[\w-]{1,64}$/', $kustom_order_id ) ) {
			return;
		}

		$url = self::get_redirect_url( $kustom_order_id );

		nocache_headers();
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Where to send the shopper: the order confirmation with its key when the session started the purchase, else
	 * without it after confirming the order with Kustom, or the cart when no order was placed.
	 *
	 * @param string $kustom_order_id The Kustom order id.
	 * @return string
	 */
	public static function get_redirect_url( $kustom_order_id ) {
		$order = kco_get_order_by_klarna_id( $kustom_order_id, '2 day ago' );
		if ( empty( $order ) ) {
			return self::get_cart_url( "no WooCommerce order was placed with Kustom order {$kustom_order_id}" );
		}

		if ( array_key_exists( $kustom_order_id, OrderCreator::get_remembered() ) ) {
			return ConfirmationUtility::get_confirmation_url( $order, $kustom_order_id );
		}

		// Only the order key reveals the order, so this session gets the page without it.
		\KCO_Logger::log( "[Express] Confirm Kustom order {$kustom_order_id} without the shopper's session." );
		kco_confirm_klarna_order( $order->get_id(), $kustom_order_id );

		// Without the key the page always says the order was received, so a failed confirmation must not land there.
		$order = wc_get_order( $order->get_id() );
		if ( empty( $order->get_date_paid() ) && ! $order->has_status( 'on-hold' ) ) {
			return self::get_cart_url( "Kustom order {$kustom_order_id} could not be confirmed, order status {$order->get_status()}" );
		}

		return remove_query_arg( 'key', $order->get_checkout_order_received_url() );
	}

	/**
	 * The cart URL, with a notice that the order could not be confirmed.
	 *
	 * @param string $reason Why, for the log.
	 * @return string
	 */
	private static function get_cart_url( $reason ) {
		\KCO_Logger::log( "[Express] Confirmation sent to the cart: {$reason}." );

		// A guest returning from another browser has no session cookie yet, which the notice needs.
		if ( WC()->session instanceof \WC_Session_Handler && ! headers_sent() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		wc_add_notice( __( 'We could not confirm your order. Please contact us if you were charged.', 'klarna-checkout-for-woocommerce' ), 'error' );

		return wc_get_cart_url();
	}
}
