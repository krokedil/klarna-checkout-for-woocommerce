<?php
namespace Krokedil\KustomCheckout\CheckoutFlow;

use Exception;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

abstract class CheckoutFlow {
	/**
	 * Process the payment depending on the flow that should be used.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public static function process_payment( $order_id ) {
		try {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				throw new Exception( __( 'Invalid order ID.', 'klarna-checkout-for-woocommerce' ) );
			}
			$handler = self::get_handler();

			\KCO_Logger::log( sprintf( 'Processing order %s|%s with flow %s.', $order->get_id(), $order->get_order_number(), get_class( $handler ) ) );

			return $handler->process( $order );
		} catch ( Exception $e ) {
			return self::error_response( $e->getMessage() );
		}
	}

	/**
	 * Get the appropriate checkout flow handler based on the settings or context.
	 *
	 * @return CheckoutFlow
	 */
	public static function get_handler() {
		$flow_setting   = SettingsUtility::get_setting( 'checkout_flow', 'embedded' );
		$change_payment = filter_input( INPUT_GET, 'change_payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_pay      = is_wc_endpoint_url( 'order-pay' );
		$blocks_enabled = BlocksUtility::is_checkout_block_enabled();

		// If the checkout block is enabled, use the embedded block flow no matter the setting.
		if ( $blocks_enabled ) {
			return new EmbeddedBlockFlow();
		}

		// If we are on the change_payment or order_pay pages, use the redirect flow no matter the setting.
		if ( $change_payment || $order_pay ) {
			return new RedirectFlow();
		}

		switch ( $flow_setting ) {
			case 'redirect':
				return new RedirectFlow();
			case 'embedded':
			default:
				return new EmbeddedFlow();
		}
	}

	/**
	 * Process the payment for the WooCommerce order.
	 *
	 * @param \WC_Order $order The WooCommerce order to be processed.
	 *
	 * @return array
	 * @throws Exception If there is an error during the payment processing.
	 */
	abstract public function process( $order );

	/**
	 * Get the Klarna order id for the order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return string|null The Klarna order id or null if not set.
	 * @throws Exception If there is an error retrieving the Klarna order id.
	 */
	public function get_klarna_order_id( $order ) {
		// For the initial subscription, the Kustom order ID should always exist in the session.
		// This also applies to (pending) renewal subscription since existing Kustom order ID is no longer valid for the renewal, we must retrieve it from the session, not the order.
		$is_subscription = function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) );

		if ( ! empty( $order ) && ! $is_subscription ) {
			$klarna_order_id = $order->get_meta( '_wc_klarna_order_id', true );
		}
		$klarna_order_id = ! empty( $klarna_order_id ) ? $klarna_order_id : WC()->session->get( 'kco_wc_order_id' );

		if ( empty( $klarna_order_id ) ) {
			throw new Exception( __( 'Klarna order ID not found.', 'klarna-checkout-for-woocommerce' ) );
		}

		return $klarna_order_id;
	}

	/**
	 * Log extra shipping debug information.
	 *
	 * @param string    $klarna_order_id The Klarna order ID.
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function debug_log_shipping( $klarna_order_id, $order ) {
		try {
			$shipping_debug_log = array(
				'kco_order_id'           => $klarna_order_id,
				'wc_order_shipping'      => $order->get_shipping_method(),
				'wc_session_shipping'    => WC()->session->get( 'chosen_shipping_methods' ),
				// selected_shipping_option is only available if shipping is displayed in iframe.
				'kco_order_shipping'     => $klarna_order['selected_shipping_option'] ?? 'N/A',
				'kco_shipping_transient' => get_transient( "kss_data_$klarna_order_id" ),
			);
			$data               = json_encode( $shipping_debug_log );
			\KCO_Logger::log( "Extra shipping debug: $data" );
		} catch ( Exception $e ) {
			\KCO_Logger::log( 'Extra shipping debug: Error generating log due to ' . $e->getMessage() );
		}
	}

	/**
	 * Get the order number from the order, with a fallback to N/A.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return string
	 */
	public function get_order_number( $order ) {
		return $order->get_order_number() ?? $order_id ?? 'N/A';
	}

	/**
	 * Get the klarna order for a order number, or throw an error.
	 *
	 * @param string $klarna_order_id The Klarna order ID.
	 *
	 * @return array
	 * @throws Exception If the Klarna order is not found or if there is an error
	 */
	public function get_klarna_order( $klarna_order_id ) {
		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );

		if ( is_wp_error( $klarna_order ) || empty( $klarna_order ) ) {
			throw new Exception( __( 'Klarna order not found or an error occurred.', 'klarna-checkout-for-woocommerce' ) );
		}

		return $klarna_order;
	}

	/**
	 * Save metadata to the order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param array     $klarna_order The Klarna order data.
	 * @param string    $checkout_flow The checkout flow type (e.g., 'embedded', 'redirect').
	 * @param bool      $save Whether to save the order metadata or not. Default is true.
	 *
	 * @return void
	 */
	public function save_order_metadata( $order, $klarna_order, $checkout_flow, $save = true ) {
		// Set Kustom Checkout flow.
		$order->update_meta_data( '_wc_klarna_checkout_flow', sanitize_text_field( $checkout_flow ) );

		// Set Kustom order ID.
		$order->update_meta_data( '_wc_klarna_order_id', sanitize_key( $klarna_order['order_id'] ) );

		// Set recurring order.
		$kco_recurring_order = isset( $klarna_order['recurring'] ) && true === $klarna_order['recurring'] ? 'yes' : 'no';
		$order->update_meta_data( '_kco_recurring_order', sanitize_key( $kco_recurring_order ) );

		// Set recurring token if it exists.
		if ( isset( $klarna_order['recurring_token'] ) ) {
			$order->update_meta_data( '_kco_recurring_token', sanitize_key( $klarna_order['recurring_token'] ) );
		}

		$environment = SettingsUtility::is_testmode() ? 'test' : 'live';
		$order->update_meta_data( '_wc_klarna_environment', $environment );

		$klarna_country = wc_get_base_location()['country'];
		$order->update_meta_data( '_wc_klarna_country', $klarna_country );

		if ( isset( $klarna_order['shipping_address']['phone'] ) ) {

			// NOTE: Since we declare support for WC v4+, and WC_Order::set_shipping_phone was only added in 5.6.0, we need to use update_meta_data instead. There is no default shipping email field in WC.
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '5.6.0', '>=' ) ) {
				$order->set_shipping_phone( sanitize_text_field( $klarna_order['shipping_address']['phone'] ) );
			} else {
				$order->update_meta_data( '_shipping_phone', sanitize_text_field( $klarna_order['shipping_address']['phone'] ) );
			}
		}

		$order->update_meta_data( '_shipping_email', sanitize_text_field( $klarna_order['shipping_address']['email'] ) );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Return error response.
	 *
	 * @param string|null $error_message The error message to return. If null, a default message will be used.
	 *
	 * @return array
	 */
	public static function error_response( $error_message = null ) {
		return array(
			'result'   => 'error',
			'messages' => $error_message ?? __( 'There was an error processing your payment. Please try again.', 'klarna-checkout-for-woocommerce' ),
		);
	}
}
