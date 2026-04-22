<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;
use WC_Product;
use WC_Tax;

/**
 * Processes stored upsell data on the push callback.
 *
 * Reads upsell metadata stored by UpsellValidator, verifies each stored line
 * against the Kustom order (authoritative source), adds the upsell products
 * to the WooCommerce order, and clears the processed metadata.
 * Throws UpsellException on any failure, reverting changes when necessary.
 */
class UpsellProcessor {
	/**
	 * The WooCommerce order.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * The Kustom order data, used as the authoritative source for validating
	 * stored upsell lines before they are added to the WC order.
	 *
	 * @var array
	 */
	private $klarna_order;

	/**
	 * New product names that where added to the order.
	 *
	 * @var string[]
	 */
	private $added_product_names = array();

	/**
	 * UpsellProcessor constructor.
	 *
	 * @param WC_Order $order        The WooCommerce order to process upsells for.
	 * @param array    $klarna_order The Kustom order fetched on the push, used to verify stored upsell lines.
	 */
	public function __construct( $order, $klarna_order ) {
		$this->order        = $order;
		$this->klarna_order = $klarna_order;
	}

	/**
	 * Process all pending upsells for the order.
	 *
	 * Reads stored upsell metadata, verifies against the Kustom order,
	 * adds products to the WC order, and recalculates totals.
	 *
	 * @return void
	 * @throws UpsellException If a product cannot be added or a stored line is not present in the Kustom order.
	 */
	public function process() {
		$order_number = $this->order->get_order_number();
		$upsell_data  = $this->order->get_meta( '_kco_upsell_data' );

		if ( empty( $upsell_data ) || ! is_array( $upsell_data ) ) {
			\KCO_Logger::log( 'Upsell processing: No pending upsell data found for WC order #' . $order_number . '. Skipping.' );
			return;
		}

		\KCO_Logger::log( 'Upsell processing: Starting for WC order #' . $order_number . ' with ' . count( $upsell_data ) . ' upsell batch(es). Data: ' . wp_json_encode( $upsell_data ) );

		foreach ( $upsell_data as $upsell_lines ) {
			$this->process_upsell_lines( $upsell_lines );
		}

		if ( empty( $this->added_product_names ) ) {
			\KCO_Logger::log( 'Upsell processing: No products were added for WC order #' . $order_number . ' after processing upsell data. This may indicate an issue with the upsell data or processing.' );
			return;
		}

		$this->order->add_order_note(
			sprintf(
				// translators: %s is a comma separated list of product names that were added to the order as part of the upsell.
				__( 'The order has been upsold with the products: %s', 'klarna-checkout-for-woocommerce' ),
				implode( ', ', $this->added_product_names )
			)
		);

		$this->order->calculate_totals();
		$this->order->delete_meta_data( '_kco_upsell_data' ); // Delete the old metadata to prevent re-processing the same upsell data if the push callback is triggered again for the same order.
		$this->order->save();

		\KCO_Logger::log( 'Upsell processing: Completed for WC order #' . $order_number . '. New order total: ' . $this->order->get_total() );
	}

	/**
	 * Process each upsell order line and add the products to the WC order.
	 *
	 * @param array $upsell_lines The upsell order lines from Kustom.
	 *
	 * @return void
	 * @throws UpsellException If a product reference is missing, product is not found, or the line is not present in the Kustom order.
	 */
	private function process_upsell_lines( $upsell_lines ) {
		if ( ! is_array( $upsell_lines ) ) {
			\KCO_Logger::log( 'ERROR Upsell processing: Stored upsell batch is not an array for WC order #' . $this->order->get_order_number() );
			throw new UpsellException( 'Stored upsell batch is not an array' );
		}

		foreach ( $upsell_lines as $line ) {
			if ( empty( $line['reference'] ) ) {
				\KCO_Logger::log( 'ERROR Upsell processing: Missing product reference in order line for WC order #' . $this->order->get_order_number() . '. Line data: ' . wp_json_encode( $line ) );
				throw new UpsellException( 'Missing product reference in order line' );
			}

			$quantity = (int) ( $line['quantity'] ?? 0 );
			if ( $quantity < 1 ) {
				\KCO_Logger::log( 'ERROR Upsell processing: Invalid quantity for line with reference ' . $line['reference'] . ' on WC order #' . $this->order->get_order_number() );
				throw new UpsellException( 'Invalid quantity in upsell line' );
			}

			// Verify the stored line against the Kustom order before adding it. This is our authentication: the callback was unsigned, but the actual order at Kustom is the source of truth.
			$this->verify_line_against_klarna_order( $line );

			$total_amount    = $this->convert_price_to_major_units( $line['total_amount'] ?? 0 );
			$discount_amount = $this->convert_price_to_major_units( $line['total_discount_amount'] ?? 0 );

			$product_reference = $line['reference'];
			$product           = self::find_product_by_reference( $product_reference );

			if ( ! $product ) {
				\KCO_Logger::log( 'ERROR Upsell processing: Product with reference ' . $product_reference . ' not found for WC order #' . $this->order->get_order_number() );
				throw new UpsellException( "Product with SKU or ID {$product_reference} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			// Calculate ex VAT prices: subtotal is before discount, total is after discount, so add the discount to get the original subtotal amount.
			$subtotal_ex_vat = $this->get_price_excluding_vat( $total_amount + $discount_amount, $product );
			$total_ex_vat    = $this->get_price_excluding_vat( $total_amount, $product );

			\KCO_Logger::log( 'Upsell processing: Adding product "' . $product->get_name() . '" (ref: ' . $product_reference . ') x' . $quantity . ' to WC order #' . $this->order->get_order_number() . '. Subtotal ex VAT: ' . $subtotal_ex_vat . ', Total ex VAT: ' . $total_ex_vat );

			$item_id = $this->order->add_product(
				$product,
				$quantity,
				array(
					'subtotal' => $subtotal_ex_vat,
					'total'    => $total_ex_vat,
				)
			);

			// Get the order item so we can flag it as a upsell item.
			$order_item = $this->order->get_item( $item_id );
			$order_item->add_meta_data( '_kco_is_upsell', 'yes' );
			$order_item->add_meta_data( '_kco_upsell_reference', $product_reference );
			$order_item->save_meta_data();
			$this->added_product_names[] = $product->get_name();
		}
	}

	/**
	 * Verify that a stored upsell line actually exists in the Kustom order with matching reference, quantity, and total amount.
	 *
	 * Since the upsell validation callback is unsigned, we re-check the stored data against the Kustom order on the push to make sure it was not tampered with by a third party.
	 *
	 * @param array $line The stored upsell order line.
	 *
	 * @return void
	 * @throws UpsellException If no matching line is found in the Kustom order.
	 */
	private function verify_line_against_klarna_order( $line ) {
		$klarna_lines = $this->klarna_order['order_lines'] ?? array();
		if ( ! is_array( $klarna_lines ) || empty( $klarna_lines ) ) {
			throw new UpsellException( 'Kustom order has no order lines; cannot verify upsell data.' );
		}

		$reference    = $line['reference'];
		$quantity     = (int) ( $line['quantity'] ?? 0 );
		$total_amount = (int) ( $line['total_amount'] ?? 0 );

		foreach ( $klarna_lines as $klarna_line ) {
			if ( ( $klarna_line['reference'] ?? null ) !== $reference ) {
				continue;
			}

			if ( (int) ( $klarna_line['quantity'] ?? 0 ) !== $quantity ) {
				continue;
			}

			if ( (int) ( $klarna_line['total_amount'] ?? 0 ) !== $total_amount ) {
				continue;
			}

			return;
		}

		\KCO_Logger::log( 'ERROR Upsell processing: Stored upsell line could not be matched against the Kustom order for WC order #' . $this->order->get_order_number() . '. Line data: ' . wp_json_encode( $line ) );
		throw new UpsellException( 'Stored upsell line does not match any line in the Kustom order.' );
	}

	/**
	 * Resolve a product reference to a WC_Product, preferring SKU lookup over ID lookup to avoid numeric-SKU collisions.
	 *
	 * @param string $reference The product reference (SKU or product ID).
	 *
	 * @return WC_Product|false The product, or false if not found.
	 */
	public static function find_product_by_reference( $reference ) {
		$product_id_from_sku = wc_get_product_id_by_sku( $reference );
		if ( $product_id_from_sku ) {
			return wc_get_product( $product_id_from_sku );
		}

		return wc_get_product( $reference );
	}

	/**
	 * Get the price of a product excluding VAT using the price including VAT from Kustom and the order.
	 *
	 * Uses {@see WC_Tax::calc_inclusive_tax()} so compound tax rates are handled correctly.
	 *
	 * @param float      $price_incl_vat The price including VAT from Kustom, already converted to major units.
	 * @param WC_Product $product        The WooCommerce product to get the VAT rate for.
	 *
	 * @return float The price excluding VAT in major units.
	 */
	private function get_price_excluding_vat( $price_incl_vat, $product ) {
		$tax_rates = $this->get_tax_rates_for_product( $product );

		if ( empty( $tax_rates ) ) {
			return round( $price_incl_vat, wc_get_price_decimals() );
		}

		$taxes     = WC_Tax::calc_inclusive_tax( $price_incl_vat, $tax_rates );
		$tax_total = array_sum( $taxes );

		return round( $price_incl_vat - $tax_total, wc_get_price_decimals() );
	}

	/**
	 * Get the tax rates applicable to a product based on the order's billing location and the product's tax class.
	 *
	 * @param WC_Product $product The WooCommerce product to get tax rates for.
	 *
	 * @return array
	 */
	private function get_tax_rates_for_product( $product ) {
		return WC_Tax::get_rates_from_location(
			$product->get_tax_class(),
			array(
				$this->order->get_billing_country(),
				$this->order->get_billing_state(),
				$this->order->get_billing_postcode(),
				$this->order->get_billing_city(),
			)
		);
	}

	/**
	 * Convert a price from minor units to major units by dividing by 100.
	 *
	 * @param int $price_minor The price in minor units (e.g. 9500).
	 *
	 * @return float The price in major units (e.g. 95.00).
	 */
	private function convert_price_to_major_units( $price_minor ) {
		return round( $price_minor / 100, wc_get_price_decimals() );
	}
}
