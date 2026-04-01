<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;
use WC_Product;
use WC_Tax;

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
		$expected_order_amount = $this->convert_price_to_major_units( $this->request_data['order_amount'] ?? 0 );

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

		if ( 'processing' !== $order->get_status() ) {
			throw new UpsellException( 'Cannot add upsell items to an order that is not in processing status.' );
		}

		return $order;
	}

	/**
	 * Get the price of a product excluding VAT using the price including VAT from Kustom and the order.
	 * This is necessary because Kustom sends unit prices including VAT, but WC order items require prices excluding VAT.
	 *
	 * @param float      $price_incl_vat The price including VAT from Kustom, already converted to major units.
	 * @param WC_Product $product The WooCommerce product to get the VAT rate for.
	 * @param WC_Order   $order The WooCommerce order, needed for tax calculations based on order context.
	 *
	 * @return float The price excluding VAT in major units.
	 */
	private function get_price_excluding_vat( $price_incl_vat, $product, $order ) {
		$product_vat_rate = $this->get_vat_rate_for_product( $product, $order );
		return round( $price_incl_vat / ( 1 + $product_vat_rate ), wc_get_price_decimals() );
	}

	/**
	 * Get the vat rate for a product based on the order's billing location and the product's tax class.
	 *
	 * @param WC_Product $product The WooCommerce product to get the VAT rate for.
	 * @param WC_Order   $order The WooCommerce order, needed for tax calculations based on order context.
	 *
	 * @return float
	 */
	private function get_vat_rate_for_product( $product, $order ) {
		// Get the rates for the product's tax class based on the order's billing location.
		$tax_rates = WC_Tax::get_rates_from_location(
			$product->get_tax_class(),
			[
				$order->get_billing_country(),
				$order->get_billing_state(),
				$order->get_billing_postcode(),
				$order->get_billing_city(),
			]
		);

		$rate = 0;
		foreach ( $tax_rates as $tax_rate ) {
			$rate += $tax_rate['rate'];
		}
		return $rate / 100; // Convert percentage to decimal (e.g. 25 to 0.25).
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
			$total_amount    = $this->convert_price_to_major_units( $line['total_amount'] ?? 0 );
			$discount_amount = $this->convert_price_to_major_units( $line['total_discount_amount'] ?? 0 );

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

			// Calculate ex VAT prices: subtotal is before discount, total is after discount, so add the discount to get the original subtotal amount.
			$subtotal_ex_vat = $this->get_price_excluding_vat( $total_amount + $discount_amount, $product, $order );
			$total_ex_vat    = $this->get_price_excluding_vat( $total_amount, $product, $order );

			$added_item_ids[] = $order->add_product(
				$product,
				$quantity,
				[
					'subtotal' => $subtotal_ex_vat,
					'total'    => $total_ex_vat,
				]
			);

			// Add the total amount to the expected order amount for final verification, using the numbers from Kustom directly.
			$expected_order_amount += $total_amount;
		}

		return $added_item_ids;
	}

	/**
	 * Convert a price from minor units to major units by dividing by 100.
	 * This is used to convert the unit prices from Kustom, which are in minor units
	 * to major units for display and calculation purposes.
	 *
	 * @param int $price_minor The price in minor units (e.g. 9500).
	 *
	 * @return float The price in major units (e.g. 95.00).
	 */
	private function convert_price_to_major_units( $price_minor ) {
		return round( $price_minor / 100, wc_get_price_decimals() );
	}

	/**
	 * Verify that the WC order total matches the expected total after adding upsell items.
	 *
	 * Allows a difference of 1 minor unit to account for rounding.
	 * If the totals do not match, the added items are reverted.
	 *
	 * @param WC_Order $order          The WooCommerce order.
	 * @param array    $added_item_ids The IDs of the added order items.
	 * @param float    $expected_total The expected order total in major units after adding upsell items.
	 *
	 * @return void
	 * @throws UpsellException If the order total does not match.
	 */
	private function verify_order_total( $order, $added_item_ids, $expected_total ) {
		$wc_total = $order->get_total();

		if ( abs( $wc_total - $expected_total ) > 0.01 ) {
			$this->revert_added_items( $order, $added_item_ids );
			throw new UpsellException( "Order total mismatch after adding upsell items. KCO order total: $expected_total, WC order total: $wc_total" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Totals are numeric and not directly from user input.
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
