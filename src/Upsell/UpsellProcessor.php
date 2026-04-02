<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;
use WC_Product;
use WC_Tax;

/**
 * Processes stored upsell data on the push callback.
 *
 * Reads upsell metadata stored by UpsellValidator, adds the upsell products
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
	 * New product names that where added to the order.
	 *
	 * @var string[]
	 */
	private $added_product_names = [];

	/**
	 * UpsellProcessor constructor.
	 *
	 * @param WC_Order $order The WooCommerce order to process upsells for.
	 */
	public function __construct( $order ) {
		$this->order = $order;
	}

	/**
	 * Process all pending upsells for the order.
	 *
	 * Reads stored upsell metadata, adds products to the WC order,
	 * and recalculates totals.
	 *
	 * @return void
	 * @throws UpsellException If a product cannot be added.
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

		return;
	}

	/**
	 * Process each upsell order line and add the products to the WC order.
	 *
	 * @param array    $upsell_lines The upsell order lines from Kustom.
	 *
	 * @return void
	 * @throws UpsellException If a product reference is missing or product is not found.
	 */
	private function process_upsell_lines( $upsell_lines ) {
		foreach ( $upsell_lines as $line ) {
			$quantity        = $line['quantity'] ?? 0;
			$total_amount    = $this->convert_price_to_major_units( $line['total_amount'] ?? 0 );
			$discount_amount = $this->convert_price_to_major_units( $line['total_discount_amount'] ?? 0 );

			if ( empty( $line['reference'] ) ) {
				\KCO_Logger::log( 'ERROR Upsell processing: Missing product reference in order line for WC order #' . $this->order->get_order_number() . '. Line data: ' . wp_json_encode( $line ) );
				throw new UpsellException( 'Missing product reference in order line' );
			}

			$product_reference = $line['reference'];
			$product           = wc_get_product( $product_reference ) ?: wc_get_product( wc_get_product_id_by_sku( $product_reference ) ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- This is done correctly here, so its safe to use.

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
	 * Get the price of a product excluding VAT using the price including VAT from Kustom and the order.
	 *
	 * @param float      $price_incl_vat The price including VAT from Kustom, already converted to major units.
	 * @param WC_Product $product The WooCommerce product to get the VAT rate for.
	 *
	 * @return float The price excluding VAT in major units.
	 */
	private function get_price_excluding_vat( $price_incl_vat, $product ) {
		$product_vat_rate = $this->get_vat_rate_for_product( $product );
		return round( $price_incl_vat / ( 1 + $product_vat_rate ), wc_get_price_decimals() );
	}

	/**
	 * Get the vat rate for a product based on the order's billing location and the product's tax class.
	 *
	 * @param WC_Product $product The WooCommerce product to get the VAT rate for.
	 *
	 * @return float
	 */
	private function get_vat_rate_for_product( $product ) {
		$tax_rates = WC_Tax::get_rates_from_location(
			$product->get_tax_class(),
			array(
				$this->order->get_billing_country(),
				$this->order->get_billing_state(),
				$this->order->get_billing_postcode(),
				$this->order->get_billing_city(),
			)
		);

		$rate = 0;
		foreach ( $tax_rates as $tax_rate ) {
			$rate += $tax_rate['rate'];
		}
		return $rate / 100;
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
