<?php
namespace Krokedil\KustomCheckout\OrderManagement\Webhooks;

use Krokedil\KustomCheckout\OrderManagement\OrderManagement;
use Krokedil\KustomCheckout\OrderManagement\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CaptureCreatedHandler class.
 *
 * Registers captures made outside of WooCommerce, e.g. in the Kustom Portal, on the WooCommerce order.
 */
class CaptureCreatedHandler {
	/**
	 * Meta key for the capture ids that have been registered from webhooks.
	 */
	const HANDLED_CAPTURES_META = '_kom_webhook_capture_ids';

	/**
	 * The order management instance.
	 *
	 * @var OrderManagement
	 */
	protected $order_management;

	/**
	 * Class constructor.
	 *
	 * @param OrderManagement $order_management The order management instance.
	 */
	public function __construct( $order_management ) {
		$this->order_management = $order_management;
	}

	/**
	 * Process a capture.created event.
	 *
	 * @param string $kustom_order_id The Kustom order id.
	 * @param string $capture_id      The Kustom capture id.
	 *
	 * @throws \Exception When the Kustom order can't be retrieved, so Action Scheduler marks the action as failed.
	 *
	 * @return void
	 */
	public function handle( $kustom_order_id, $capture_id ) {
		if ( ! Settings::is_capture_sync_enabled() ) {
			return;
		}

		$order = kco_get_order_by_klarna_id( $kustom_order_id );

		// The order may belong to another store using the same Kustom account, so this isn't an error.
		if ( ! $order ) {
			$this->log( "No WooCommerce order found for Kustom order {$kustom_order_id}. Capture {$capture_id} ignored." );
			return;
		}

		$order_number = $order->get_order_number();

		if ( 'kco' !== $order->get_payment_method() || $order->get_meta( '_kom_disconnect' ) ) {
			$this->log( "Order {$order_number} is not synced with Kustom. Capture {$capture_id} ignored." );
			return;
		}

		// The capture was made from WooCommerce, which has already registered it.
		if ( $order->get_meta( '_wc_klarna_capture_id' ) === $capture_id ) {
			$this->log( "Capture {$capture_id} on order {$order_number} was made from WooCommerce. Ignored." );
			return;
		}

		$handled_captures = (array) $order->get_meta( self::HANDLED_CAPTURES_META );
		if ( in_array( $capture_id, $handled_captures, true ) ) {
			$this->log( "Capture {$capture_id} on order {$order_number} has already been registered. Ignored." );
			return;
		}

		// The webhook only holds ids, so the current state is read from Kustom. This also confirms the capture exists.
		$klarna_order = $this->order_management->retrieve_klarna_order( $order->get_id() );
		if ( is_wp_error( $klarna_order ) ) {
			$this->log( "Could not retrieve Kustom order {$kustom_order_id} for order {$order_number}. " . $klarna_order->get_error_message() );
			throw new \Exception( 'Could not retrieve the Kustom order.' );
		}

		$capture = $this->find_capture( $klarna_order, $capture_id );
		if ( ! $capture ) {
			$this->log( "Capture {$capture_id} was not found on Kustom order {$kustom_order_id}. Ignored." );
			return;
		}

		$handled_captures[] = $capture_id;
		$order->update_meta_data( self::HANDLED_CAPTURES_META, array_values( array_filter( $handled_captures ) ) );

		$note = sprintf(
			// translators: 1: Capture amount, 2: Capture ID.
			__( 'Kustom order captured outside of WooCommerce. Capture amount: %1$s. Capture ID: %2$s.', 'klarna-checkout-for-woocommerce' ),
			wc_price( $capture->captured_amount / 100, array( 'currency' => $order->get_currency() ) ),
			$capture_id
		);

		if ( 'CAPTURED' !== $klarna_order->status ) {
			$order->add_order_note( $note . ' ' . __( 'The order is only partially captured, so the order status is unchanged.', 'klarna-checkout-for-woocommerce' ) );
			$order->save();
			$this->log( "Registered partial capture {$capture_id} on order {$order_number}." );
			return;
		}

		// Lets refunds from WooCommerce work, which require a capture id.
		if ( empty( $order->get_meta( '_wc_klarna_capture_id' ) ) ) {
			$order->update_meta_data( '_wc_klarna_capture_id', $capture_id );
		}

		/**
		 * Filters the order statuses that are set to completed when the Kustom order is captured outside of WooCommerce.
		 *
		 * @param string[]  $statuses The order statuses, without the "wc-" prefix. Default array( 'processing', 'on-hold' ).
		 * @param \WC_Order $order    The WooCommerce order.
		 */
		$completable_statuses = apply_filters( 'kom_webhook_capture_completable_statuses', array( 'processing', 'on-hold' ), $order );

		if ( ! in_array( $order->get_status(), $completable_statuses, true ) ) {
			$order->add_order_note( $note );
			$order->save();
			$this->log( "Registered capture {$capture_id} on order {$order_number}. Status {$order->get_status()} left unchanged." );
			return;
		}

		// The order is already captured, so completing it must not trigger a capture from WooCommerce.
		$capture_callback = array( $this->order_management, 'capture_klarna_order' );
		$priority         = has_action( 'woocommerce_order_status_completed', $capture_callback );
		if ( false !== $priority ) {
			remove_action( 'woocommerce_order_status_completed', $capture_callback, $priority );
		}

		$order->update_status( 'completed', $note );

		if ( false !== $priority ) {
			add_action( 'woocommerce_order_status_completed', $capture_callback, $priority );
		}

		$this->log( "Registered capture {$capture_id} on order {$order_number} and completed the order." );
	}

	/**
	 * Find a capture on the Kustom order.
	 *
	 * @param object $klarna_order The Kustom order.
	 * @param string $capture_id   The capture id.
	 *
	 * @return object|null
	 */
	protected function find_capture( $klarna_order, $capture_id ) {
		foreach ( $klarna_order->captures ?? array() as $capture ) {
			if ( isset( $capture->capture_id ) && $capture->capture_id === $capture_id ) {
				return $capture;
			}
		}

		return null;
	}

	/**
	 * Log a webhook message.
	 *
	 * @param string $message The message.
	 *
	 * @return void
	 */
	protected function log( $message ) {
		\KCO_Logger::log( '[Webhook capture.created]: ' . $message );
	}
}
