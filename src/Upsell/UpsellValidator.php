<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;

/**
 * Validates and processes upsell requests from Kustom.
 *
 * Validates that the Klarna and WooCommerce orders are in a valid state for upsell,
 * adds the upsell products to the WC order, and verifies the resulting total.
 * Throws UpsellException on any failure, reverting changes when necessary.
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
	 * Process the upsell request.
	 *
	 * Validates all preconditions, adds upsell items to the WC order,
	 * and verifies the resulting order total matches the expected amount.
	 *
	 * @return void
	 * @throws UpsellException If any validation step fails.
	 */
	public function process() {
		$this->validate_input();
		$this->get_validated_klarna_order();
		$order = $this->get_validated_wc_order();

		$upsell_lines          = $this->request_data['upsell_order_lines'];
		$expected_order_amount = $this->request_data['order_amount'] ?? 0;

		$added_item_ids = $this->process_upsell_lines( $order, $upsell_lines, $expected_order_amount );

		$order->calculate_totals();
		$order->save();

		$this->verify_order_total( $order, $added_item_ids, $expected_order_amount );
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
	 * Ensures the order exists, has status checkout_complete,
	 * and that the payment type allows amount increase.
	 *
	 * @return array The Klarna order data.
	 * @throws UpsellException If the Klarna order is invalid or in wrong state.
	 */
	private function get_validated_klarna_order() {
		$klarna_order = KCO_WC()->api->get_klarna_order( $this->kco_order_id );

		if ( is_wp_error( $klarna_order ) ) {
			throw new UpsellException( "Could not retrieve KCO order for order ID {$this->kco_order_id}" );
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
			throw new UpsellException( "Order not found for KCO order ID {$this->kco_order_id}", 404 );
		}

		if ( ! empty( $order->get_meta( '_wc_klarna_capture_id' ) ) || ! empty( $order->get_meta( '_wc_klarna_cancelled' ) ) ) {
			throw new UpsellException( 'Cannot add upsell items to an order that has already been captured or cancelled.' );
		}

		if ( 'processing' !== $order->get_status() ) {
			throw new UpsellException( 'Cannot add upsell items to an order that is not in processing status.' );
		}

		return $order;
	}

	/**
	 * Process each upsell order line and add the products to the WC order.
	 *
	 * @param WC_Order $order                 The WooCommerce order.
	 * @param array    $upsell_lines          The upsell order lines from Kustom.
	 * @param int      $expected_order_amount The expected order amount in minor units, updated with each line total.
	 *
	 * @return array The IDs of the added order items.
	 * @throws UpsellException If a product reference is missing or product is out of stock.
	 */
	private function process_upsell_lines( $order, $upsell_lines, &$expected_order_amount ) {
		$added_item_ids = [];

		foreach ( $upsell_lines as $line ) {
			$quantity        = $line['quantity'] ?? 0;
			$unit_price      = $line['unit_price'] ?? 0;
			$total_amount    = $line['total_amount'] ?? 0;
			$discount_amount = $line['total_discount_amount'] ?? 0;

			$unit_discount_amount = $quantity > 0 ? $discount_amount / $quantity : 0;

			if ( empty( $line['reference'] ) ) {
				throw new UpsellException( 'Missing product reference in order line' );
			}

			$product = wc_get_product( $line['reference'] );
			if ( ! $product || ! $product->is_in_stock() ) {
				throw new UpsellException( "Product with SKU {$line['reference']} is out of stock" );
			}

			// Calculate prices in major units. Unit price from Kustom is inc VAT in minor units.
			$unit_price_original_major   = $unit_price / 100;
			$unit_price_discounted_major = ( $unit_price - $unit_discount_amount ) / 100;

			// Get the VAT rate by comparing inc/ex tax for 1 unit of currency.
			$product_vat_rate = wc_get_price_including_tax( $product, [ 'price' => 1, 'qty' => 1, 'order' => $order ] )
				- wc_get_price_excluding_tax( $product, [ 'price' => 1, 'qty' => 1, 'order' => $order ] );

			// Calculate ex VAT prices: subtotal is before discount, total is after discount.
			$subtotal_ex_vat = round( $unit_price_original_major / ( 1 + $product_vat_rate ), wc_get_price_decimals() );
			$total_ex_vat    = round( $unit_price_discounted_major / ( 1 + $product_vat_rate ), wc_get_price_decimals() );

			$added_item_ids[] = $order->add_product( $product, $quantity, [ 'subtotal' => $subtotal_ex_vat, 'total' => $total_ex_vat ] );

			$expected_order_amount += $total_amount;
		}

		return $added_item_ids;
	}

	/**
	 * Verify that the WC order total matches the expected total after adding upsell items.
	 *
	 * Allows a difference of 1 minor unit to account for rounding.
	 * If the totals do not match, the added items are reverted.
	 *
	 * @param WC_Order $order          The WooCommerce order.
	 * @param array    $added_item_ids The IDs of the added order items.
	 * @param int      $expected_total The expected order total in minor currency units.
	 *
	 * @return void
	 * @throws UpsellException If the order total does not match.
	 */
	private function verify_order_total( $order, $added_item_ids, $expected_total ) {
		$wc_total = round( $order->get_total(), 2 ) * 100;

		if ( abs( $wc_total - $expected_total ) > 1 ) {
			$this->revert_added_items( $order, $added_item_ids );
			throw new UpsellException( "Order total mismatch after adding upsell items. KCO order total: $expected_total, WC order total: $wc_total" );
		}
	}

	/**
	 * Remove added upsell items from the order to revert it to its original state.
	 *
	 * @param WC_Order $order    The WooCommerce order.
	 * @param array    $item_ids The IDs of the items to remove.
	 *
	 * @return void
	 */
	private function revert_added_items( $order, $item_ids ) {
		foreach ( $item_ids as $item_id ) {
			$order->remove_item( $item_id );
		}

		$order->calculate_totals();
		$order->save();
	}
}
