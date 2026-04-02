<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;

/**
 * Validates upsell requests from Kustom and stores them for later processing.
 *
 * Validates that the Klarna and WooCommerce orders are in a valid state for upsell,
 * and that the referenced products exist and are in stock.
 * Stores validated upsell data as order metadata for processing on the push callback.
 * Throws UpsellException on any validation failure.
 */
class UpsellValidator {
	/**
	 * The decoded request body from Kustom.
	 *
	 * @var array
	 */
	private $request_data;

	/**
	 * The Kustom Checkout order ID.
	 *
	 * @var string
	 */
	private $kco_order_id;

	/**
	 * UpsellValidator constructor.
	 *
	 * @param array  $request_data The decoded request body.
	 * @param string $kco_order_id The Kustom Checkout order ID.
	 */
	public function __construct( $request_data, $kco_order_id ) {
		$this->request_data = $request_data;
		$this->kco_order_id = $kco_order_id;
	}

	/**
	 * Validate the upsell request and store it for later processing.
	 *
	 * Validates all preconditions and stores the upsell data as order metadata.
	 * The actual processing (adding items to the order) happens on the push callback
	 * via UpsellProcessor.
	 *
	 * @return void
	 * @throws UpsellException If any validation step fails.
	 */
	public function process() {
		$this->validate_input();
		\KCO_Logger::log( $this->kco_order_id . ': Upsell request input validated.' );

		$this->get_validated_klarna_order();
		\KCO_Logger::log( $this->kco_order_id . ': Kustom order validated for upsell (status checkout_complete, payment type allows increase).' );

		$order = $this->get_validated_wc_order();
		\KCO_Logger::log( $this->kco_order_id . ': WC order #' . $order->get_order_number() . ' validated for upsell.' );

		$upsell_lines = $this->request_data['upsell_order_lines'];
		$this->validate_upsell_lines( $upsell_lines );
		\KCO_Logger::log( $this->kco_order_id . ': Upsell order lines validated (' . count( $upsell_lines ) . ' lines).' );

		$this->store_upsell_data( $order, $upsell_lines );
		\KCO_Logger::log( $this->kco_order_id . ': Upsell data stored on WC order #' . $order->get_order_number() . '.' );
	}

	/**
	 * Validate that the request data and KCO order ID are present.
	 *
	 * @return void
	 * @throws UpsellException If input is invalid.
	 */
	private function validate_input() {
		if ( empty( $this->request_data ) || empty( $this->kco_order_id ) ) {
			throw new UpsellException( 'Invalid JSON or missing KCO order ID' );
		}

		if ( ! isset( $this->request_data['upsell_order_lines'] ) ) {
			throw new UpsellException( 'Missing upsell_order_lines in request data' );
		}
	}

	/**
	 * Fetch and validate the Klarna order.
	 *
	 * Ensures the order exists and has status checkout_complete with a payment type that allows increase.
	 *
	 * @return array The Klarna order data.
	 * @throws UpsellException If the Klarna order is invalid or in wrong state.
	 */
	private function get_validated_klarna_order() {
		$klarna_order = KCO_WC()->api->get_klarna_order( $this->kco_order_id );

		if ( is_wp_error( $klarna_order ) ) {
			throw new UpsellException( "Could not retrieve KCO order for order ID {$this->kco_order_id}" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- ID is escaped on assignment.
		}

		if ( 'checkout_complete' !== $klarna_order['status'] || ! $klarna_order['payment_type_allows_increase'] ) {
			throw new UpsellException( 'KCO order status is not checkout_complete or payment type does not allow increase, cannot add upsell items.' );
		}

		return $klarna_order;
	}

	/**
	 * Find and validate the WooCommerce order.
	 *
	 * Ensures the order exists, has not been captured or cancelled,
	 * and is in processing status.
	 *
	 * @return WC_Order The validated WooCommerce order.
	 * @throws UpsellException If the WC order is not found or in invalid state.
	 */
	private function get_validated_wc_order() {
		$order = kco_get_order_by_klarna_id( $this->kco_order_id );

		if ( empty( $order ) ) {
			throw new UpsellException( "Order not found for KCO order ID {$this->kco_order_id}", 404 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- ID is escaped on assignment.
		}

		if ( ! empty( $order->get_meta( '_wc_klarna_capture_id' ) ) || ! empty( $order->get_meta( '_wc_klarna_cancelled' ) ) ) {
			throw new UpsellException( 'Cannot add upsell items to an order that has already been captured or cancelled.' );
		}

		if ( 'on-hold' !== $order->get_status() ) {
			throw new UpsellException( 'Cannot add upsell items to an order that is not on hold.' );
		}

		return $order;
	}

	/**
	 * Validate each upsell order line.
	 *
	 * Ensures each line has a valid product reference and that the product exists and is in stock.
	 *
	 * @param array $upsell_lines The upsell order lines from Kustom.
	 *
	 * @return void
	 * @throws UpsellException If a product reference is missing, product not found, or out of stock.
	 */
	private function validate_upsell_lines( $upsell_lines ) {
		foreach ( $upsell_lines as $line ) {
			if ( empty( $line['reference'] ) ) {
				throw new UpsellException( 'Missing product reference in order line' );
			}

			$product_reference = $line['reference'];
			$product           = wc_get_product( $product_reference ) ?: wc_get_product( wc_get_product_id_by_sku( $product_reference ) ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- This is done correctly here, so its safe to use.

			if ( ! $product ) {
				throw new UpsellException( "Product with SKU or ID {$product_reference} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			if ( ! $product->is_in_stock() ) {
				throw new UpsellException( "Product with SKU or ID {$product_reference} is out of stock" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
	}

	/**
	 * Store the upsell data as order metadata for later processing.
	 *
	 * Appends to existing upsell data to support multiple upsells per order.
	 *
	 * @param WC_Order $order        The WooCommerce order.
	 * @param array    $upsell_lines The upsell order lines from Kustom.
	 *
	 * @return void
	 */
	private function store_upsell_data( $order, $upsell_lines ) {
		$existing_data = $order->get_meta( '_kco_upsell_data' );

		if ( empty( $existing_data ) || ! is_array( $existing_data ) ) {
			$existing_data = array();
		}

		$existing_data[] = $upsell_lines;

		$order->update_meta_data( '_kco_upsell_data', $existing_data );
		$order->save();
	}
}
