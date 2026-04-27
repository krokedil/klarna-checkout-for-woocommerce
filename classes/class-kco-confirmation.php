<?php
/**
 * Confirmation class.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Krokedil\KustomCheckout\Utility\SigningKeyUtility;

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
		$order_id        = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT );
		$order_key       = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Return if we don't have our parameters set.
		if ( empty( $kco_confirm ) || empty( $klarna_order_id ) || empty( $order_key ) ) {
			return;
		}

		if ( ! empty( $order_id ) ) {
			$order    = wc_get_order( $order_id );
			$order_id = ! empty( $order ) && hash_equals( $order->get_order_key(), $order_key ) ? absint( $order_id ) : wc_get_order_id_by_order_key( $order_key );
		} else {
			$order_id = wc_get_order_id_by_order_key( $order_key );
		}

		// Return if we cant find an order id.
		if ( empty( $order_id ) ) {
			return;
		}

		// Confirm the order.
		KCO_Logger::log( $klarna_order_id . ': Confirm the Kustom order from the confirmation page.' );
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
	 * Validate the EPM order to ensure we should continue with the EPM purchase or not.
	 *
	 * @param string   $kustom_order_id The Kustom order id.
	 * @param string   $epm The name of the external payment method.
	 * @param WC_Order $order The WooCommerce order object.
	 * @return bool True if the order is valid for processing the external payment, false if not.
	 */
	private function validate_epm_order( $kustom_order_id, $epm, $order ) {
		$wc_order_id     = $order->get_id();
		$wc_order_number = $order->get_order_number();
		$log_context     = "Kustom order id {$kustom_order_id}, WC order id {$wc_order_id} (number {$wc_order_number}), external payment {$epm}";

		// Get the Kustom order details from the API to be able to validate the order.
		$kustom_order = KCO_WC()->api->get_klarna_order( $kustom_order_id );

		// Ensure we got a valid Kustom order response.
		if ( is_wp_error( $kustom_order ) || empty( $kustom_order ) ) {
			KCO_Logger::log( "Failed to retrieve Kustom order for EPM validation. {$log_context}." );
			return false;
		}

		$kustom_status  = $kustom_order['status'] ?? '';
		$merchant_ref_1 = $kustom_order['merchant_reference1'] ?? '';
		$merchant_ref_2 = $kustom_order['merchant_reference2'] ?? '';

		// Validate the signing key to ensure the order is valid and was not tampered with.
		if ( ! SigningKeyUtility::validate_from_kustom_order( $kustom_order, $kustom_order_id ) ) {
			KCO_Logger::log( "Failed to validate the signing key from the Kustom order. {$log_context}." );
			return false;
		}

		// Ensure the Kustom order is in the correct status.
		if ( 'checkout_incomplete' !== $kustom_status ) {
			KCO_Logger::log( "The Kustom order is in status {$kustom_status} which is not valid for EPM. {$log_context}." );
			return false;
		}

		// Ensure the Kustom order has the correct merchant reference 1 that matches the WooCommerce order number.
		if ( $wc_order_number !== $merchant_ref_1 ) {
			KCO_Logger::log( "Failed EPM validation: merchant_reference1 ({$merchant_ref_1}) does not match the WooCommerce order number. {$log_context}." );
			return false;
		}

		// Ensure the Kustom order has the correct merchant reference 2 that matches the WooCommerce order id.
		if ( absint( $merchant_ref_2 ) !== $wc_order_id ) {
			KCO_Logger::log( "Failed EPM validation: merchant_reference2 ({$merchant_ref_2}) does not match the WooCommerce order id. {$log_context}." );
			return false;
		}

		// Ensure the WooCommerce order has the correct Kustom order id stored in the meta.
		if ( $order->get_meta( '_wc_klarna_order_id' ) !== $kustom_order_id ) {
			KCO_Logger::log( "Failed EPM validation: the WooCommerce order does not have the correct Kustom order id stored in the meta. {$log_context}." );
			return false;
		}

		return true;
	}

	/**
	 * Initiates a Kustom External Payment Method payment.
	 *
	 * @param string $epm The name of the external payment method.
	 * @param string $order_id The WooCommerce order id.
	 * @param string $klarna_order_id The Kustom order id.
	 * @return void
	 */
	public function run_kepm( $epm, $order_id, $klarna_order_id ) {
		$order = wc_get_order( $order_id );

		// Try to retrieve the WC_Order using the Kustom order id.
		if ( empty( $order ) && ! empty( $klarna_order_id ) ) {
			$order = kco_get_order_by_klarna_id( $klarna_order_id, '2 day ago' );

			if ( ! empty( $order ) ) {
				$order_id = $order->get_id();
			}
		}

		// Check if we have a order.
		if ( empty( $order ) ) {
			wc_add_notice( __( 'Failed getting the order for the external payment.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return;
		}

		if ( empty( $klarna_order_id ) ) {
			/**
			 * Deprecated notice for missing Kustom order id in EPM URL.
			 *
			 * This is done to make sure that existing integrations that use external payment methods without including the Kustom order id in the URL do not break immediately.
			 * But in the future this will be required to be able to properly verify the order,
			 * and prevent the wrong order being processed in cases where multiple orders have been created in WooCommerce with the same KCO order id.
			 *
			 * To add this to your integration, make sure to include the Kustom order id in the URL as a kco_order_id parameter by adding &kco_order_id={checkout.order.id} to the redirect URL when registering the EPM.
			 *
			 * @see https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/external-payment-method-epm/#example-code-bacs
			 */
			KCO_Logger::log( "Running external payment method {$epm} without Kustom order id in the URL. This will be required in the future to ensure proper verification of the order." );
			wc_deprecated_argument( 'run_kepm', '2.20.0', 'Please include the Kustom order id in the redirect URL by adding &kco_order_id={checkout.order.id} when registering the EPM. If this is not done, the external payment method may not function correctly in the future.' );
		} elseif ( ! $this->validate_epm_order( $klarna_order_id, $epm, $order ) ) { // If we fail to validate the purchase, we should not continue with processing the external payment.
			wc_add_notice( __( 'We couldn\'t verify your payment. Please try again.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return;
		}

		$payment_methods = WC()->payment_gateways->get_available_payment_gateways();

		// Check if the payment method is available.
		if ( ! isset( $payment_methods[ $epm ] ) ) {
			wc_add_notice( __( 'Failed to find the payment method for the external payment.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return;
		}

		// Everything is fine, redirect to the URL specified by the gateway.
		WC()->session->set( 'chosen_payment_method', $epm );
		$order->set_payment_method( $payment_methods[ $epm ] );
		$order->save();
		$result = $payment_methods[ $epm ]->process_payment( $order_id );

		// Check if the result is good.
		if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
			wc_add_notice( __( 'Something went wrong with the external payment. Please try again', 'klarna-checkout-for-woocommerce' ), 'error' );
			return;
		}

		wp_redirect( $result['redirect'] ); // phpcs:ignore
		exit;
	}

	/**
	 * Lock a KCO order id and WooCommerce order id combination to prevent multiple simultaneous confirmations.
	 *
	 * @param string $kco_id The KCO order id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return bool True if the lock was successful, false if there is already a lock for the given combination.
	 */
	public static function lock_kco_confirmation( $kco_id, $order_id ) {
		$key = "kco_confirm_{$kco_id}_{$order_id}";
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $key, true, 'kco_locks', MINUTE_IN_SECONDS );
		}

		return set_transient( $key, true, MINUTE_IN_SECONDS );
	}

	/**
	 * Unlock a KCO order id and WooCommerce order id combination after the confirmation process is done.
	 *
	 * @param string $kco_id The KCO order id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return void
	 */
	public static function unlock_kco_confirmation( $kco_id, $order_id ) {
		$key = "kco_confirm_{$kco_id}_{$order_id}";
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, 'kco_locks' );
			return;
		}

		delete_transient( $key );
	}
}
KCO_Confirmation::get_instance();
