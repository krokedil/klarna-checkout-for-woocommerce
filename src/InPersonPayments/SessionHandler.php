<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetSession;
use Krokedil\KustomCheckout\InPersonPayments\Request\Put\RequestPutCancelSession;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place an in-person payment changes the order.
 *
 * The browser poll and the webhook both routinely report the same session, so every
 * path goes through sync(), which reads the order back from the database and does
 * nothing to one that is already paid.
 */
class SessionHandler {

	/**
	 * The session is still with the customer at the counter.
	 *
	 * @var string
	 */
	public const WAITING = 'waiting';

	/**
	 * The customer paid.
	 *
	 * @var string
	 */
	public const PAID = 'paid';

	/**
	 * The sale did not happen.
	 *
	 * @var string
	 */
	public const FAILED = 'failed';

	/**
	 * The device reports a payment that does not match the order, so a person has to look.
	 *
	 * @var string
	 */
	public const HELD = 'held';

	/**
	 * How long a completion may hold its lock before it is presumed dead, in seconds.
	 *
	 * @var int
	 */
	public const LOCK_LIFETIME = 60;

	/**
	 * The statuses that mean the session is still open.
	 *
	 * @var string[]
	 */
	public const OPEN_STATUSES = array( 'CREATED', 'ACTIVE' );

	/**
	 * Read the session an order is waiting for and apply it.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array|\WP_Error
	 */
	public static function refresh( $order ) {
		$session = self::fetch( $order->get_meta( Gateway::SESSION_META ) );

		return is_wp_error( $session ) ? $session : self::sync( $order, $session );
	}

	/**
	 * Ask the device to give up on the session, then apply the answer.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array|\WP_Error
	 */
	public static function cancel( $order ) {
		$session_id = $order->get_meta( Gateway::SESSION_META );
		if ( empty( $session_id ) ) {
			return new \WP_Error( 'no_session', __( 'This order has no in-person payment session.', 'klarna-checkout-for-woocommerce' ) );
		}

		$session = ( new RequestPutCancelSession( array( 'session_id' => $session_id ) ) )->request();

		return is_wp_error( $session ) ? $session : self::sync( $order, $session );
	}

	/**
	 * Read a session from Kustom.
	 *
	 * @param string $session_id The session id.
	 * @return array|\WP_Error
	 */
	public static function fetch( $session_id ) {
		if ( empty( $session_id ) ) {
			return new \WP_Error( 'no_session', __( 'This order has no in-person payment session.', 'klarna-checkout-for-woocommerce' ) );
		}

		return ( new RequestGetSession( array( 'session_id' => $session_id ) ) )->request();
	}

	/**
	 * Apply a session to its order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param array     $session The session as Kustom reports it.
	 * @return array
	 */
	public static function sync( $order, $session ) {
		// Read the order back, because the other signal may have completed it since.
		$order  = wc_get_order( $order->get_id() );
		$status = strtoupper( (string) ( $session['status'] ?? '' ) );

		if ( $order->is_paid() ) {
			return self::outcome( self::PAID, $order->get_meta( Gateway::STATUS_META ) );
		}

		if ( 'FINALIZED' === $status ) {
			if ( ! self::lock( $order->get_id() ) ) {
				// The other signal is completing the order right now; the next poll sees it paid.
				return self::outcome( self::WAITING, 'FINALIZED' );
			}

			try {
				return self::complete( $order, $session );
			} finally {
				self::unlock( $order->get_id() );
			}
		}

		if ( in_array( $status, self::OPEN_STATUSES, true ) || '' === $status ) {
			self::remember_status( $order, $status );
			return self::outcome( self::WAITING, $status );
		}

		return self::fail( $order, $status );
	}

	/**
	 * The customer paid on the device. Goods change hands at the counter, so there is
	 * nothing left to fulfil.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param array     $session The session as Kustom reports it.
	 * @return array
	 */
	private static function complete( $order, $session ) {
		$expected = (int) round( (float) $order->get_total() * 100 );
		$paid     = isset( $session['order_amount'] ) ? (int) $session['order_amount'] : null;

		if ( $paid !== $expected ) {
			return self::hold( $order, $expected, $paid );
		}

		$kustom_order_id = sanitize_text_field( (string) ( $session['order_id'] ?? '' ) );
		$session_id      = sanitize_text_field( (string) ( $session['session_id'] ?? $order->get_meta( Gateway::SESSION_META ) ) );

		$order->update_meta_data( Gateway::STATUS_META, 'FINALIZED' );

		if ( '' !== $kustom_order_id ) {
			$order->update_meta_data( '_wc_klarna_order_id', $kustom_order_id );
			$order->update_meta_data( '_wc_klarna_environment', SettingsUtility::is_testmode() ? 'test' : 'live' );
			$order->update_meta_data( '_wc_klarna_country', wc_get_base_location()['country'] );
		}

		$order->save();

		// The Kustom order id, not the session id: order management refunds against it.
		$order->payment_complete( '' !== $kustom_order_id ? $kustom_order_id : $session_id );

		$order->add_order_note(
			sprintf(
				/* translators: %s: the Kustom order id. */
				__( 'Payment collected on the Kustom POS device. Kustom order id: %s', 'klarna-checkout-for-woocommerce' ),
				'' === $kustom_order_id ? '-' : $kustom_order_id
			)
		);

		if ( ! $order->has_status( array( 'completed', 'refunded' ) ) ) {
			$order->update_status( 'completed' );
		}

		/**
		 * Fires once, after an in-person payment has completed its order.
		 *
		 * @since 2.22.0
		 *
		 * @param int   $order_id The WooCommerce order id.
		 * @param array $session The finalized session as Kustom reports it.
		 */
		do_action( 'kco_ipp_payment_complete', $order->get_id(), $session );

		return self::outcome( self::PAID, 'FINALIZED' );
	}

	/**
	 * The device collected a different amount than the order is for. The order is left
	 * as it is, because neither marking it paid nor failing it would be true.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param int       $expected The order total in minor units.
	 * @param int|null  $paid What the session says was collected, in minor units.
	 * @return array
	 */
	private static function hold( $order, $expected, $paid ) {
		$order->add_order_note(
			sprintf(
				/* translators: 1: the amount the device collected, 2: the order total, both in minor units. */
				__( 'The Kustom POS device reports a payment of %1$s, but the order total is %2$s. The order was not completed. Check the payment in the Kustom portal.', 'klarna-checkout-for-woocommerce' ),
				null === $paid ? '-' : $paid,
				$expected
			)
		);

		\KCO_Logger::log( sprintf( 'ERROR Kustom IPP session %s for order %s reports amount %s, order total is %s.', $order->get_meta( Gateway::SESSION_META ), $order->get_order_number(), null === $paid ? '-' : $paid, $expected ) );

		return self::outcome( self::HELD, 'FINALIZED', __( 'The amount paid on the device does not match this order. Do not take the payment again: check the order notes and the Kustom portal.', 'klarna-checkout-for-woocommerce' ) );
	}

	/**
	 * Take the completion lock for an order. A plain insert against the unique option
	 * name is atomic where `add_option()` is not: that one updates on a duplicate key.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return bool Whether this caller holds the lock.
	 */
	public static function lock( $order_id ) {
		global $wpdb;

		$name = self::lock_name( $order_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- The insert has to race, which the option functions cannot.
		$suppressed = $wpdb->suppress_errors( true );
		$taken      = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no')", $name, time() ) );
		$wpdb->suppress_errors( $suppressed );

		if ( $taken ) {
			return true;
		}

		$held_since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( $held_since > 0 && time() - $held_since > self::LOCK_LIFETIME ) {
			self::unlock( $order_id );
			return self::lock( $order_id );
		}

		return false;
	}

	/**
	 * Release the completion lock for an order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public static function unlock( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Pairs with the insert in lock().
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::lock_name( $order_id ) ) );
	}

	/**
	 * The option the completion lock for an order lives in.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string
	 */
	private static function lock_name( $order_id ) {
		return 'kco_ipp_completing_' . absint( $order_id );
	}

	/**
	 * The sale did not happen. The order is left unpaid so the salesperson can try again.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param string    $status The terminal session status.
	 * @return array
	 */
	private static function fail( $order, $status ) {
		$reason = self::reason( $status );

		self::remember_status( $order, $status );

		// Failed rather than cancelled: it is Woo's status for a payment that did not go
		// through, and the salesperson retries from the restored cart.
		if ( ! $order->has_status( array( 'failed', 'cancelled' ) ) ) {
			$order->update_status( 'failed', $reason );
		}

		\KCO_Logger::log( sprintf( 'Kustom IPP session %s for order %s ended as %s.', $order->get_meta( Gateway::SESSION_META ), $order->get_order_number(), $status ) );

		return self::outcome( self::FAILED, $status, $reason );
	}

	/**
	 * What the salesperson is told about a session that ended without a payment.
	 *
	 * @param string $status The terminal session status.
	 * @return string
	 */
	private static function reason( $status ) {
		switch ( $status ) {
			case 'CANCELLED':
				return __( 'The payment was cancelled on the device. Please try again.', 'klarna-checkout-for-woocommerce' );
			case 'EXPIRED':
				return __( 'The payment session expired before it was paid. Please try again.', 'klarna-checkout-for-woocommerce' );
			case 'FAILED':
				return __( 'The payment failed on the device. Please try again.', 'klarna-checkout-for-woocommerce' );
			default:
				return __( 'The payment could not be collected on the device. Please try again.', 'klarna-checkout-for-woocommerce' );
		}
	}

	/**
	 * Record the last session status seen.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param string    $status The session status.
	 * @return void
	 */
	private static function remember_status( $order, $status ) {
		if ( '' === $status || $order->get_meta( Gateway::STATUS_META ) === $status ) {
			return;
		}

		$order->update_meta_data( Gateway::STATUS_META, $status );
		$order->save();
	}

	/**
	 * Build the answer both the poll and the webhook work from.
	 *
	 * @param string $state One of waiting, paid, failed or held.
	 * @param string $status The session status behind it.
	 * @param string $message What to show the salesperson.
	 * @return array
	 */
	private static function outcome( $state, $status, $message = '' ) {
		return array(
			'state'   => $state,
			'status'  => $status,
			'message' => $message,
		);
	}
}
