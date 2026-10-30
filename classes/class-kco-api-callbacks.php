<?php
/**
 * API Callbacks class.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Krokedil\KustomCheckout\Logging\LogMasking;
use Krokedil\KustomCheckout\Upsell\UpsellException;
use Krokedil\KustomCheckout\Upsell\UpsellFallback;
use Krokedil\KustomCheckout\Upsell\UpsellProcessor;
use Krokedil\KustomCheckout\Upsell\UpsellRecommendations;
use Krokedil\KustomCheckout\Upsell\UpsellValidator;

/**
 * KCO_API_Callbacks class.
 *
 * Class that handles KCO API callbacks.
 */
class KCO_API_Callbacks {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var KCO_API_Callbacks
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
		add_action( 'woocommerce_api_kco_wc_address_update', array( $this, 'address_update_cb' ) );
		add_action( 'woocommerce_api_kco_wc_upsell_validation', array( $this, 'upsell_validation_cb' ) );
		add_action( 'woocommerce_api_kco_wc_upsell', array( $this, 'upsell_cb' ) );
	}

	/**
	 * Return the upsell recommendations Kustom shows on the confirmation page.
	 *
	 * @return void
	 */
	public function upsell_cb() {
		$data         = json_decode( file_get_contents( 'php://input' ), true );
		$kco_order_id = filter_input( INPUT_GET, 'kco_wc_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order        = empty( $kco_order_id ) ? false : kco_get_order_by_klarna_id( $kco_order_id );
		KCO_WC()->logger->log( "Upsell recommendations request for Kustom order ID $kco_order_id: " . wp_json_encode( LogMasking::mask_response( $data ) ) );

		if ( empty( $order ) || ! is_array( $data ) ) {
			KCO_WC()->logger->log( "ERROR Upsell recommendations callback without a matching order or valid body for Kustom order ID: $kco_order_id" );
			wp_send_json(
				array(
					'upsell_lines' => array(),
					'empty'        => true,
				)
			);
		}

		$response = ( new UpsellRecommendations( $order, $data ) )->get_response();
		KCO_WC()->logger->log( "Upsell recommendations for Kustom order ID $kco_order_id: " . wp_json_encode( $response ) );
		wp_send_json( $response );
	}

	/**
	 * Process the upsell validation request from Kustom.
	 *
	 * Delegates to UpsellValidator for all business logic.
	 *
	 * @return void
	 */
	public function upsell_validation_cb() {
		$post_body    = file_get_contents( 'php://input' );
		$data         = json_decode( $post_body, true );
		$kco_order_id = filter_input( INPUT_GET, 'kco_wc_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		KCO_WC()->logger->log( "Upsell validation callback hit for order: $kco_order_id" );
		KCO_WC()->logger->log( 'Upsell validation request data: ' . wp_json_encode( LogMasking::mask_response( $data ) ) );

		try {
			$validator = new UpsellValidator( $data ?? array(), $kco_order_id ?? '' );
			$validator->process();
			KCO_WC()->logger->log( 'Upsell validation successful for order: ' . $kco_order_id );
			wp_send_json( array() );
		} catch ( UpsellException $e ) {
			KCO_WC()->logger->log( 'ERROR Upsell validation failed for order ' . $kco_order_id . ': ' . $e->getMessage() );
			wp_send_json( array( 'error' => $e->getMessage() ), $e->get_status_code() );
		}
	}

	/**
	 * Push callback function.
	 */
	public function push_cb() {
		/**
		 * 1. Handle POST request
		 * 2. Request the order from Kustom
		 * 4. Acknowledge the order
		 * 5. Send merchant_reference1
		 */
		$klarna_order_id = filter_input( INPUT_GET, 'kco_wc_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		// Do nothing if there's no Kustom Checkout order ID.
		if ( empty( $klarna_order_id ) ) {
			return;
		}

		$this->process_push( $klarna_order_id );
	}

	/**
	 * Handle a push notification for a Kustom order. Also used by the express push route.
	 *
	 * @param string $klarna_order_id The Kustom order ID.
	 */
	public function process_push( $klarna_order_id ) {
		KCO_WC()->logger->log( 'Push callback hit for order: ' . $klarna_order_id );

		// Let other plugins hook into the push notification.
		// Used by Klarna_Checkout_Subscription::handle_push_cb_for_payment_method_change().
		/**
		 * Fires when a push notification is received from Kustom, before the WooCommerce order is processed.
		 *
		 * @param string $klarna_order_id The Kustom order ID.
		 */
		do_action( 'wc_klarna_push_cb', $klarna_order_id );

		$this->process_push_for_order( $klarna_order_id );
	}

	/**
	 * Run the push processing pipeline for a given Kustom order ID.
	 *
	 * Shared by the push webhook and the Action Scheduler fallback so that a
	 * missed push can still be processed identically later.
	 *
	 * @param string $klarna_order_id The Kustom order ID.
	 * @return void
	 */
	public function process_push_for_order( $klarna_order_id ) {
		$order = kco_get_order_by_klarna_id( $klarna_order_id );
		if ( empty( $order ) ) {
			// Backup order creation.
			KCO_WC()->logger->log( 'ERROR Push callback but no existing WC order found for Kustom order ID ' . stripslashes_deep( wp_json_encode( $klarna_order_id ) ) );
			return;
		}

		// Serialize the push, the fallback, the thank-you page and the upsell validation for the order, so the stored upsell data is not processed twice.
		$order_id = $order->get_id();
		if ( ! KCO_Confirmation::lock_kco_confirmation( $klarna_order_id, $order_id, 'upsell' ) ) {
			KCO_WC()->logger->log( 'Push processing for Kustom order ID ' . $klarna_order_id . ' skipped; another run is already in progress.' );
			return;
		}

		$did_lock = false;
		try {
			// Reload the order, since another run may have changed it before the lock was taken.
			$order = wc_get_order( $order_id );

			// The merchant cancelled the order before it was paid, e.g. while it waited for upsells, so do not complete it again.
			if ( empty( $order->get_date_paid() ) && $order->has_status( 'cancelled' ) ) {
				KCO_WC()->logger->log( 'Push processing for Kustom order ID ' . $klarna_order_id . ' skipped; WC order #' . $order->get_order_number() . ' was cancelled before it was paid.' );
				return;
			}

			if ( apply_filters( 'kco_wc_lock_confirmation', false, $klarna_order_id, $order_id ) ) {
				$did_lock = KCO_Confirmation::lock_kco_confirmation( $klarna_order_id, $order_id );
				if ( ! $did_lock ) {
					KCO_WC()->logger->log( "[PUSH CALLBACK]: Simultaneous push callback for Kustom order ID $klarna_order_id and WooCommerce order ID $order_id. Stopping process." );
					return;
				}
			}

			// Get the Kustom order data.
			/**
			 * Filters the Kustom order data retrieved during the push notification callback.
			 *
			 * @param array|false $klarna_order The Kustom order data from the order management API, or false on failure.
			 */
			$klarna_order = apply_filters(
				'kco_wc_api_callbacks_push_klarna_order',
				KCO_WC()->api->get_klarna_om_order( $klarna_order_id )
			);

			if ( empty( $klarna_order ) || is_wp_error( $klarna_order ) ) {
				KCO_WC()->logger->log( '[PUSH CALLBACK]: ERROR Push callback failed to get Kustom order data for Kustom order ID ' . stripslashes_deep( wp_json_encode( $klarna_order_id ) ) );
				return;
			}

			// Add any stored upsells before the order totals are validated and the order is acknowledged. Also when upsell was turned off while the order waited.
			try {
				( new UpsellProcessor( $order, $klarna_order ) )->process();
				$order = wc_get_order( $order->get_id() ); // Refresh the order after processing the upsell, to make sure we have the latest data.
			} catch ( UpsellException $e ) {
				KCO_WC()->logger->log( 'ERROR Push callback failed to process upsell data for Kustom order ID ' . stripslashes_deep( wp_json_encode( $klarna_order_id ) ) . ': ' . $e->getMessage() );
				return;
			}

			if ( ! kco_validate_order_total( $klarna_order, $order ) || ! kco_validate_order_content( $klarna_order, $order ) ) {
				return;
			}

			// The Woo order was already created. Check if order status was set (in process_payment_handler).
			if ( empty( $order->get_date_paid() ) ) {
				if ( 'ACCEPTED' === $klarna_order['fraud_status'] ) {
					$order->payment_complete( $klarna_order_id );
					// translators: Kustom order ID.
					$note = sprintf( __( 'Payment via Kustom Checkout, order ID: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
					$order->add_order_note( $note );
					/**
					 * Triggers after an accepted Kustom order has been completed.
					 *
					 * @link https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#add-additional-checkboxes Add additional checkboxes
					 * @param int   $order_id     The WooCommerce order ID.
					 * @param array $klarna_order The Kustom order data.
					 */
					do_action( 'kco_wc_payment_complete', $order_id, $klarna_order );
				} elseif ( 'REJECTED' === $klarna_order['fraud_status'] ) {
					$order->update_status( 'on-hold', __( 'Kustom Checkout order was rejected.', 'klarna-checkout-for-woocommerce' ) );
				} elseif ( 'PENDING' === $klarna_order['fraud_status'] ) {
					// translators: Kustom order ID.
					$note = sprintf( __( 'Kustom order is under review, order ID: %s.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
					$order->update_status( 'on-hold', $note );
				}
			}

			// Acknowledge order in Kustom.
			KCO_WC()->api->acknowledge_klarna_order( $klarna_order_id );

			// Set the merchant references for the order.
			KCO_WC()->api->set_merchant_reference( $klarna_order_id, $order_id );

			// Push processing succeeded; drop any pending fallback action for this order.
			UpsellFallback::cancel( $klarna_order_id );
		} finally {
			if ( $did_lock ) {
				KCO_Confirmation::unlock_kco_confirmation( $klarna_order_id, $order_id );
			}
			KCO_Confirmation::unlock_kco_confirmation( $klarna_order_id, $order_id, 'upsell' );
		}
	}

	/**
	 * Address update callback function.
	 * Response must be sent to Kustom API.
	 *
	 * @link https://docs.kustom.co/v3/checkout/additional-resources/server-side-callbacks#address-update
	 * @ref  https://github.com/mmartche/coach/blob/30022c266089fc7499c54e149883e951c288dc9f/catalog/controller/extension/payment/klarna_checkout.php#L509
	 */
	public function address_update_cb() {
		// Currently disabled, because of how response body needs to be calculated in WooCommerce.
	}
}

KCO_API_Callbacks::get_instance();
