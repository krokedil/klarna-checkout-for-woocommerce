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
		if ( '' === $kustom_order_id ) {
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
			\KCO_Logger::log( "[Express] Confirmation for Kustom order {$kustom_order_id}, but no WooCommerce order was placed with it." );

			// A guest returning from another browser has no session cookie yet, which the notice needs.
			if ( WC()->session instanceof \WC_Session_Handler && ! headers_sent() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			wc_add_notice( __( 'We could not find your order. Please contact us if you were charged.', 'klarna-checkout-for-woocommerce' ), 'error' );

			return wc_get_cart_url();
		}

		if ( array_key_exists( $kustom_order_id, OrderCreator::get_remembered() ) ) {
			return ConfirmationUtility::get_confirmation_url( $order, $kustom_order_id );
		}

		// Only the order key reveals the order, so this session gets the page without it.
		\KCO_Logger::log( "[Express] Confirm Kustom order {$kustom_order_id} without the shopper's session." );
		kco_confirm_klarna_order( $order->get_id(), $kustom_order_id );

		return remove_query_arg( 'key', $order->get_checkout_order_received_url() );
	}
}
