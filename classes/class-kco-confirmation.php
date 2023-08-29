<?php
/**
 * Confirmation class.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Confirmation class.
 *
 * Class that handles the confirmation step.
 */
class KCO_Confirmation {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'confirm_order' ), 999 );
		add_action( 'init', array( $this, 'check_if_external_payment' ) );
	}

	/**
	 * Redirects the customer to the proper thank you page.
	 *
	 * @return void
	 */
	public function confirm_order() {
		$kco_confirm     = filter_input( INPUT_GET, 'kco_confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$klarna_order_id = filter_input( INPUT_GET, 'kco_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key       = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Return if we dont have our parameters set.
		if ( empty( $kco_confirm ) || empty( $klarna_order_id ) || empty( $order_key ) ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );

		// Return if we cant find an order id.
		if ( empty( $order_id ) ) {
			return;
		}

		// Confirm the order.
		KCO_Logger::log( $klarna_order_id . ': Confirm the klarna order from the confirmation page.' );
		kco_confirm_klarna_order( $order_id, $klarna_order_id );
		kco_unset_sessions();
	}

	/**
	 * Checks if we have an external payment method on page load.
	 *
	 * @return void
	 */
	public function check_if_external_payment() {
		$epm             = filter_input( INPUT_GET, 'kco-external-payment', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id        = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$klarna_order_id = filter_input( INPUT_GET, 'kco_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $epm ) ) {
			$this->run_kepm( $epm, $order_id, $klarna_order_id );
		}
	}

	/**
	 * Initiates a Klarna External Payment Method payment.
	 *
	 * @param string $epm The name of the external payment method.
	 * @param string $order_id The WooCommerce order id.
	 * @param string $klarna_order_id The Klarna order id.
	 * @return void
	 */
	public function run_kepm( $epm, $order_id, $klarna_order_id ) {
		$order = wc_get_order( $order_id );

		// Try to retrieve the WC_Order using the Klarna order id.
		if ( empty( $order ) && ! empty( $klarna_order_id ) ) {
			$order = kco_get_order_by_klarna_id( $klarna_order_id, '2 day ago' );

			if ( ! empty( $order ) ) {
				$order_id = $order->get_id();
			}
		}

		// Check if we have a order.
		if ( empty( $order ) ) {
			wc_print_notice( __( 'Failed getting the order for the external payment.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return;
		}

		$payment_methods = WC()->payment_gateways->get_available_payment_gateways();
		// Check if the payment method is available.
		if ( ! isset( $payment_methods[ $epm ] ) ) {
			wc_print_notice( __( 'Failed to find the payment method for the external payment.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return;
		}
		// Everything is fine, redirect to the URL specified by the gateway.
		WC()->session->set( 'chosen_payment_method', $epm );
		$order->set_payment_method( $payment_methods[ $epm ] );
		$order->save();
		$result = $payment_methods[ $epm ]->process_payment( $order_id );
		// Check if the result is good.
		if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
			wc_print_notice( __( 'Something went wrong with the external payment. Please try again', 'klarna-checkout-for-woocommerce' ), 'error' );
			return;
		}
		wp_redirect( $result['redirect'] ); // phpcs:ignore
		exit;
	}
}
KCO_Confirmation::get_instance();
