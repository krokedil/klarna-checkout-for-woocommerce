<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;
use WC_Product;
use WC_Tax;

/**
 * Processes stored upsell data on the push callback.
 *
 * Reads upsell metadata stored by UpsellValidator, verifies each stored batch
 * against the Kustom order (authoritative source), adds the upsell products
 * to the WooCommerce order, and clears the processed metadata.
 * Kustom adds an upsell in the same request that sends the validation callback, so a batch that is not
 * in the Kustom order was rejected by Kustom and is dropped, unless the processor is told to keep it.
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
	 * Whether to drop stored batches that are not in the Kustom order, instead of keeping them for a later run.
	 *
	 * @var bool
	 */
	private $drop_unmatched;

	/**
	 * New product names that were added to the order.
	 *
	 * @var string[]
	 */
	private $added_product_names = array();

	/**
	 * UpsellProcessor constructor.
	 *
	 * @param WC_Order $order          The WooCommerce order to process upsells for.
	 * @param array    $klarna_order   The Kustom order fetched on the push, used to verify stored upsell lines.
	 * @param bool     $drop_unmatched Whether to drop stored batches that are not in the Kustom order.
	 */
	public function __construct( $order, $klarna_order, $drop_unmatched = true ) {
		$this->order          = $order;
		$this->klarna_order   = $klarna_order;
		$this->drop_unmatched = $drop_unmatched;
	}

	/**
	 * Process all pending upsells for the order.
	 *
	 * Adds the stored batches that are in the Kustom order, drops or keeps the ones that are not,
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

		// Each batch is one upsell from one validation callback, so it is added or dropped as a whole.
		$handled       = array();
		$dropped_names = array();
		foreach ( $upsell_data as $upsell_lines ) {
			if ( $this->batch_is_in_klarna_order( $upsell_lines ) ) {
				$this->process_upsell_lines( $upsell_lines );
				$handled[] = $upsell_lines;
			} elseif ( $this->drop_unmatched ) {
				\KCO_Logger::log( 'Upsell processing: Dropping an upsell batch that is not in the Kustom order for WC order #' . $order_number . '. Data: ' . wp_json_encode( $upsell_lines ) );
				$dropped_names = array_merge( $dropped_names, self::get_line_names( $upsell_lines ) );
				$handled[]     = $upsell_lines;
			} else {
				\KCO_Logger::log( 'Upsell processing: Keeping an upsell batch that is not in the Kustom order yet for WC order #' . $order_number . '. Data: ' . wp_json_encode( $upsell_lines ) );
			}
		}

		if ( empty( $handled ) ) {
			return;
		}

		if ( ! empty( $this->added_product_names ) ) {
			$this->order->add_order_note(
				sprintf(
					// translators: %s is a comma separated list of product names that were added to the order as part of the upsell.
					__( 'The order has been upsold with the products: %s', 'klarna-checkout-for-woocommerce' ),
					implode( ', ', $this->added_product_names )
				)
			);
			$this->order->calculate_totals();
		}

		if ( ! empty( $dropped_names ) ) {
			$this->order->add_order_note(
				sprintf(
					// translators: %s is a comma separated list of product names that were not added to the order.
					__( 'The upsell with the products %s was not added to the order, since Kustom did not add it to the Kustom order.', 'klarna-checkout-for-woocommerce' ),
					implode( ', ', $dropped_names )
				)
			);
		}

		$this->remove_handled_batches( $handled );
		$this->order->save();

		\KCO_Logger::log( 'Upsell processing: Completed for WC order #' . $order_number . '. New order total: ' . $this->order->get_total() );
	}

	/**
	 * Remove the handled batches from the stored upsell data, keeping any batch stored while the Kustom order was fetched.
	 *
	 * @param array[] $handled The batches that were added or dropped.
	 *
	 * @return void
	 */
	private function remove_handled_batches( $handled ) {
		// Re-read the stored data, since the validation callback may have stored a new upsell after this order was loaded.
		$stored_order = wc_get_order( $this->order->get_id() );
		$stored       = $stored_order ? $stored_order->get_meta( '_kco_upsell_data' ) : array();

		$remaining = array();
		foreach ( is_array( $stored ) ? $stored : array() as $batch ) {
			if ( ! in_array( $batch, $handled, true ) ) {
				$remaining[] = $batch;
			}
		}

		if ( empty( $remaining ) ) {
			$this->order->delete_meta_data( '_kco_upsell_data' );
		} else {
			$this->order->update_meta_data( '_kco_upsell_data', $remaining );
		}
	}

	/**
	 * Get the product names of a stored batch, for the order note.
	 *
	 * @param mixed $upsell_lines The stored upsell lines.
	 *
	 * @return string[]
	 */
	private static function get_line_names( $upsell_lines ) {
		$names = array();
		foreach ( is_array( $upsell_lines ) ? $upsell_lines : array() as $line ) {
			$names[] = (string) ( $line['name'] ?? $line['reference'] ?? '' );
		}

		return array_filter( $names );
	}

	/**
	 * Whether every line of a stored batch is in the Kustom order.
	 *
	 * @param mixed $upsell_lines The stored upsell lines.
	 *
	 * @return bool
	 */
	private function batch_is_in_klarna_order( $upsell_lines ) {
		if ( empty( $upsell_lines ) || ! is_array( $upsell_lines ) ) {
			return false;
		}

		foreach ( $upsell_lines as $line ) {
			if ( empty( $line['reference'] ) || ! $this->line_is_in_klarna_order( $line ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Process each upsell order line and add the products to the WC order.
	 *
	 * @param array $upsell_lines The upsell order lines from Kustom.
	 *
	 * @return void
	 * @throws UpsellException If a product reference is missing or the product is not found.
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

			if ( empty( $item_id ) ) {
				\KCO_Logger::log( 'ERROR Upsell processing: add_product returned no item ID for reference ' . $product_reference . ' on WC order #' . $this->order->get_order_number() );
				throw new UpsellException( "Failed to add product {$product_reference} to order" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			// Get the order item so we can flag it as an upsell item.
			$order_item = $this->order->get_item( $item_id );
			if ( ! $order_item ) {
				\KCO_Logger::log( 'ERROR Upsell processing: get_item returned false for item ID ' . $item_id . ' (reference ' . $product_reference . ') on WC order #' . $this->order->get_order_number() );
				throw new UpsellException( "Failed to retrieve added order item for reference {$product_reference}" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$order_item->add_meta_data( '_kco_is_upsell', 'yes' );
			$order_item->add_meta_data( '_kco_upsell_reference', $product_reference );
			$order_item->save_meta_data();
			$this->added_product_names[] = $product->get_name();
		}
	}

	/**
	 * Whether a stored upsell line is in the Kustom order with matching reference, quantity, and total amount.
	 *
	 * Since the upsell validation callback is unsigned, the stored data is checked against the Kustom order before it is added.
	 *
	 * @param array $line The stored upsell order line.
	 *
	 * @return bool
	 */
	private function line_is_in_klarna_order( $line ) {
		$klarna_lines = $this->klarna_order['order_lines'] ?? array();
		$reference    = $line['reference'];
		$quantity     = (int) ( $line['quantity'] ?? 0 );
		$total_amount = (int) ( $line['total_amount'] ?? 0 );

		foreach ( is_array( $klarna_lines ) ? $klarna_lines : array() as $klarna_line ) {
			if ( ( $klarna_line['reference'] ?? null ) === $reference
				&& (int) ( $klarna_line['quantity'] ?? 0 ) === $quantity
				&& (int) ( $klarna_line['total_amount'] ?? 0 ) === $total_amount ) {
				return true;
			}
		}

		return false;
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
