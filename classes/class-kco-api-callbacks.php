<?php
/**
 * API Callbacks class.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_API_Callbacks class.
 *
 * Class that handles KCO API callbacks.
 */
class KCO_API_Callbacks {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return KCO_API_Callbacks The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * KCO_API_Callbacks constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_kco_wc_push', array( $this, 'push_cb' ) );
		add_action( 'woocommerce_api_kco_wc_notification', array( $this, 'notification_cb' ) );
		add_action( 'woocommerce_api_kco_wc_address_update', array( $this, 'address_update_cb' ) );
		add_action( 'kco_wc_punted_notification', array( $this, 'kco_wc_punted_notification_cb' ), 10, 2 );
	}

	/**
	 * Push callback function.
	 */
	public function push_cb() {
		/**
		 * 1. Handle POST request
		 * 2. Request the order from Klarna
		 * 4. Acknowledge the order
		 * 5. Send merchant_reference1
		 */
		$klarna_order_id = filter_input( INPUT_GET, 'kco_wc_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		// Do nothing if there's no Klarna Checkout order ID.
		if ( empty( $klarna_order_id ) ) {
			return;
		}

		KCO_WC()->logger->log( 'Push callback hit for order: ' . $klarna_order_id );

		// Let other plugins hook into the push notification.
		// Used by Klarna_Checkout_Subscription::handle_push_cb_for_payment_method_change().
		do_action( 'wc_klarna_push_cb', $klarna_order_id );

		$order = kco_get_order_by_klarna_id( $klarna_order_id );
		if ( empty( $order ) ) {
			// Backup order creation.
			KCO_WC()->logger->log( 'ERROR Push callback but no existing WC order found for Klarna order ID ' . stripslashes_deep( wp_json_encode( $klarna_order_id ) ) );
			return;

		}

		$order_id = $order->get_id();
		if ( $order ) {
			// Get the Klarna order data.
			$klarna_order = apply_filters(
				'kco_wc_api_callbacks_push_klarna_order',
				KCO_WC()->api->get_klarna_om_order( $klarna_order_id )
			);

			// The Woo order was already created. Check if order status was set (in process_payment_handler).
			if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
				if ( 'ACCEPTED' === $klarna_order['fraud_status'] ) {
					$order->payment_complete( $klarna_order_id );
					// translators: Klarna order ID.
					$note = sprintf( __( 'Payment via Klarna Checkout, order ID: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
					$order->add_order_note( $note );
					do_action( 'kco_wc_payment_complete', $order_id, $klarna_order );
				} elseif ( 'REJECTED' === $klarna_order['fraud_status'] ) {
					$order->update_status( 'on-hold', __( 'Klarna Checkout order was rejected.', 'klarna-checkout-for-woocommerce' ) );
				} elseif ( 'PENDING' === $klarna_order['fraud_status'] ) {
					// translators: Klarna order ID.
					$note = sprintf( __( 'Klarna order is under review, order ID: %s.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
					$order->update_status( 'on-hold', $note );
				}
			}

			// Acknowledge order in Klarna.
			KCO_WC()->api->acknowledge_klarna_order( $klarna_order_id );

			// Set the merchant references for the order.
			KCO_WC()->api->set_merchant_reference( $klarna_order_id, $order_id );

		} else {
			// Backup order creation.
			KCO_WC()->logger->log( 'ERROR Push callback but no existing WC order found for Klarna order ID ' . stripslashes_deep( wp_json_encode( $klarna_order_id ) ) );
		}
	}

	/**
	 * Notification callback function, used for pending orders.
	 */
	public function notification_cb() {
		/**
		 * Notification callback URL has Klarna Order ID (kco_wc_order_id) in it.
		 *
		 * 1. Get Klarna Order ID
		 * 2. Try to find matching WooCommerce order, to see if it was created
		 * 3. If WooCommerce order does not exist, that means regular creation failed AND confirmation callback
		 *    either hasn't happened yet or failed. In this case, schedule a single event, 5 minutes from now
		 *    and try to get WooCommerce order then.
		 * 4. If WooCommerce order does exist, fire the hook.
		 */

		$klarna_order_id = filter_input( INPUT_GET, 'kco_wc_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! empty( $klarna_order_id ) ) {

			$order = kco_get_order_by_klarna_id( $klarna_order_id );

			if ( ! empty( $order ) ) {
				$order_id = $order->get_id();
			}
		}

		if ( isset( $order_id ) ) {
			do_action( 'wc_klarna_notification_listener' );
		} else {
			$post_body = file_get_contents( 'php://input' );
			$data      = json_decode( $post_body, true );
			wp_schedule_single_event( time() + 300, 'kco_wc_punted_notification', array( $klarna_order_id, $data ) );
		}
	}

	/**
	 * Punted notification callback.
	 *
	 * @param string $klarna_order_id Klarna order ID.
	 * @param array  $data Klarna order data.
	 */
	public function kco_wc_punted_notification_cb( $klarna_order_id, $data ) {
		do_action( 'wc_klarna_notification_listener', $klarna_order_id, $data );
	}

	/**
	 * Address update callback function.
	 * Response must be sent to Klarna API.
	 *
	 * @link https://developers.klarna.com/api/#checkout-api-callbacks-address-update
	 * @ref  https://github.com/mmartche/coach/blob/30022c266089fc7499c54e149883e951c288dc9f/catalog/controller/extension/payment/klarna_checkout.php#L509
	 */
	public function address_update_cb() {
		// Currently disabled, because of how response body needs to be calculated in WooCommerce.
	}
}

KCO_API_Callbacks::get_instance();
