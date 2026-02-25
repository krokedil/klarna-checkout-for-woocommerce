<?php
/**
 * Pending orders
 *
 * Provides Klarna pending orders functionality.
 *
 * @package OrderManagement
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Klarna_Pending_Orders class.
 *
 * Handles pending orders.
 */
class PendingOrders {

	/**
	 * Notification listener for Pending orders.
	 *
	 * @param string $klarna_order_id Klarna order ID.
	 * @param array  $data The data for the order.
	 *
	 * @link https://developers.klarna.com/en/us/kco-v3/pending-orders
	 */
	public static function notification_listener( $klarna_order_id = null, $data = null ) {
		$order_id = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $klarna_order_id ) ) {
			$klarna_order_id = filter_input( INPUT_GET, 'kco_wc_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		// Get order id from klarna order id.
		if ( empty( $order_id ) && ! empty( $klarna_order_id ) ) {
			$order_id = self::get_order_id_from_klarna_order_id( $klarna_order_id );
		}

		// Get klarna order id from order id.
		if ( empty( $klarna_order_id ) && ! empty( $order_id ) ) {
			$klarna_order_id = self::get_klarna_order_id_from_order_id( $order_id );
		}

		// Bail if we do not have the order id or Klarna order id.
		if ( empty( $order_id ) || empty( $klarna_order_id ) ) {
			return;
		}

		// Check the order status for the klarna order. Bail if it does not exist in order management.
		$klarna_order = OrderManagement::get_instance()->retrieve_klarna_order( $order_id );
		if ( is_wp_error( $klarna_order ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// If a paid date is set, the order has already been processed. It is therefore not a pending order.
		if ( ! empty( $order->get_date_paid() ) ) {
			return;
		}

		// Use the order from Klarna for the fraud status check.
		if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
			$order->payment_complete( $klarna_order_id );
			$order->add_order_note( 'Payment with Klarna is accepted.' );
		} elseif ( 'REJECTED' === $klarna_order->fraud_status || 'STOPPED' === $klarna_order->fraud_status ) {
			// Set meta field so order cancellation doesn't trigger Klarna API requests.
			$order->update_meta_data( '_wc_klarna_pending_to_cancelled', true );
			$order->update_status( 'cancelled', 'Klarna order rejected.' );
			$order->save();
			wc_mail(
				get_option( 'admin_email' ),
				'Klarna order rejected',
				sprintf(
					'Klarna has identified order %1$s, Klarna Reference %2$s as high risk and request that you do not ship this order. Please contact the Klarna Fraud Team to resolve.',
					$order->get_order_number(),
					$klarna_order->order_id
				)
			);
		}
	}

	/**
	 * Gets WooCommerce order ID from Klarna order ID.
	 *
	 * @param string $klarna_order_id The klarna order id.
	 * @return $order_id
	 */
	private static function get_order_id_from_klarna_order_id( $klarna_order_id ) {
		$orders = wc_get_orders(
			array(
				'meta_query' => array(
					'meta_key'   => '_wc_klarna_order_id',
					'meta_value' => $klarna_order_id,
					'compare'    => '=',
				),
			)
		);

		$order = reset( $orders );

		if ( empty( $orders ) ) {
			return;
		}

		return $order->get_id();
	}

	/**
	 * Get Klarna order id from WooCommerce order id.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string|bool
	 */
	private static function get_klarna_order_id_from_order_id( $order_id ) {
		return wc_get_order( $order_id )->get_meta( '_wc_klarna_order_id', true );
	}
}
