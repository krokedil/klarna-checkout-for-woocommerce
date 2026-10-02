<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free orders class.
 *
 * Handles zero-value Kustom orders (e.g. a free-trial subscription or a fully-discounted cart) so TMS
 * shipping data still gets saved to the WooCommerce order, since WooCommerce skips the normal payment
 * flow for orders that need no payment.
 */
class FreeOrders {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_no_payment_needed_redirect', array( $this, 'handle_free_kco_orders' ), 20, 2 );
	}

	/**
	 * Set Kustom data in order even for free orders and finally reply to Kustom's JS validation event so order can proceed to confirmation step.
	 *
	 * @param string $redirect_url The redirect to thankyou page url.
	 * @param object $order The WooCommerce order.
	 * @return string the modified url, to be able to reply to Kustom's JS validation event.
	 */
	public function handle_free_kco_orders( $redirect_url, $order ) {

		if ( 'kco' === $order->get_payment_method() ) {
			$order_id = $order->get_id();

			if ( $this->process_payment_handler( $order_id ) ) {
				// Base64 encoded timestamp to always have a fresh URL for on hash change event.
				$redirect_url = '#klarna-success=' . base64_encode( microtime() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to give a unique nondescript string.
			} else {
				$redirect_url = '#error';
			}
		}

		return $redirect_url;
	}

	/**
	 * Process the payment with information from Kustom and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return mixed
	 */
	public function process_payment_handler( $order_id ) {
		// Get the Kustom order ID.
		$order = wc_get_order( $order_id );
		if ( is_object( $order ) && $order->get_transaction_id() ) {
			$klarna_order_id = $order->get_transaction_id();
		} else {
			$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
		}

		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
		if ( ! $klarna_order ) {
			return false;
		}

		if ( $order_id && $klarna_order ) {
			$order = wc_get_order( $order_id );

			// Set WC order transaction ID.
			$order->update_meta_data( '_wc_klarna_order_id', sanitize_key( $klarna_order['order_id'] ) );

			$order->update_meta_data( '_transaction_id', sanitize_key( $klarna_order['order_id'] ) );

			$environment = SettingsUtility::is_testmode() ? 'test' : 'live';
			$order->update_meta_data( '_wc_klarna_environment', $environment );

			$klarna_country = wc_get_base_location()['country'];
			$order->update_meta_data( '_wc_klarna_country', $klarna_country );

			// Set shipping phone and email.
			$shipping_phone = $klarna_order['shipping_address']['phone'] ?? $klarna_order['billing_address']['phone'] ?? '';
			$shipping_email = $klarna_order['shipping_address']['email'] ?? $klarna_order['billing_address']['email'] ?? '';
			$order->update_meta_data( '_shipping_phone', sanitize_text_field( $shipping_phone ) );
			$order->update_meta_data( '_shipping_email', sanitize_text_field( $shipping_email ) );

			$order->save();

			// Update the order with new confirmation page url.
			$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id, $order_id );

			// Let other plugins hook into this sequence.
			do_action( 'kco_wc_process_payment', $order_id, $klarna_order );

			// Check that the transaction id got set correctly.
			if ( $order->get_meta( '_transaction_id', true ) === $klarna_order_id ) {
				return true;
			}
		}
		// Return false if we get here. Something went wrong.
		return false;
	}
}
