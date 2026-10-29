<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;
use WC_Product;
use WC_Tax;

/**
 * Builds the upsell recommendations returned to Kustom on the upsell callback, from the
 * cross-sell, upsell or Kustom upsell links of the ordered products. Custom leaves it to the filter.
 */
class UpsellRecommendations {
	/**
	 * Product meta key holding the Kustom upsell product IDs.
	 */
	const KUSTOM_UPSELLS_META_KEY = '_kco_upsell_ids';

	/**
	 * The WooCommerce order the recommendations are for.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * The decoded request body from Kustom.
	 *
	 * @var array
	 */
	private $request_data;

	/**
	 * UpsellRecommendations constructor.
	 *
	 * @param WC_Order $order        The WooCommerce order.
	 * @param array    $request_data The decoded request body from Kustom.
	 */
	public function __construct( $order, $request_data ) {
		$this->order        = $order;
		$this->request_data = $request_data;
	}

	/**
	 * Whether the order could get any upsell recommendations, to decide at confirmation if it should wait for the push.
	 * Always true for the none setting, where Kustom picks the products in its portal.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	public static function has_recommendations( $order ) {
		$settings = get_option( 'woocommerce_kco_settings', array() );
		if ( 'none' === ( $settings['upsell_recommendations'] ?? 'none' ) ) {
			return true;
		}

		// Kustom only sends max_upsell_amount on the upsell callback, so no product is left out for its price here.
		$request_data = array(
			'upsell_possible'   => true,
			'max_upsell_amount' => PHP_INT_MAX,
		);
		$response     = ( new self( $order, $request_data ) )->get_response();

		return empty( $response['empty'] );
	}

	/**
	 * Get the response body for the upsell callback.
	 *
	 * @return array
	 */
	public function get_response() {
		$upsell_lines = array();
		if ( ! empty( $this->request_data['upsell_possible'] ) ) {
			foreach ( $this->get_products() as $product ) {
				$line = $this->get_upsell_line( $product );
				if ( $line ) {
					$upsell_lines[] = $line;
				}
			}
		}

		/**
		 * Filters the upsell recommendations returned to Kustom.
		 *
		 * @param array    $upsell_lines The upsell lines, formatted as Kustom upsell_lines.
		 * @param WC_Order $order        The WooCommerce order the recommendations are for.
		 * @param array    $request_data The request body from Kustom, including upsell_possible and max_upsell_amount. When the order is confirmed,
		 *                               the filter also runs to check if the order should wait for an upsell, with max_upsell_amount set to PHP_INT_MAX.
		 */
		$upsell_lines = apply_filters( 'kco_wc_upsell_recommendations', $upsell_lines, $this->order, $this->request_data );

		if ( empty( $upsell_lines ) ) {
			return array(
				'upsell_lines' => array(),
				'empty'        => true,
			);
		}

		return array( 'upsell_lines' => array_values( $upsell_lines ) );
	}

	/**
	 * Get the products to recommend based on the upsell_recommendations setting.
	 *
	 * @return WC_Product[]
	 */
	private function get_products() {
		$settings = get_option( 'woocommerce_kco_settings', array() );
		$source   = $settings['upsell_recommendations'] ?? 'none';
		if ( ! in_array( $source, array( 'cross_sells', 'upsells', 'kustom_upsells' ), true ) ) {
			return array();
		}

		$excluded_ids = array();
		$linked_ids   = array();
		foreach ( $this->order->get_items() as $item ) {
			$product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : false;
			if ( ! $product ) {
				continue;
			}

			$excluded_ids[] = $product->get_id();
			// Variations have no linked products of their own, they are set on the parent.
			$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
			if ( $parent ) {
				$linked_ids = array_merge( $linked_ids, self::get_linked_ids( $parent, $source ) );
			}
		}

		// Upsells validated by Kustom but not yet added to the order by the push callback.
		$pending_batches = $this->order->get_meta( '_kco_upsell_data' );
		foreach ( is_array( $pending_batches ) ? $pending_batches : array() as $batch ) {
			foreach ( is_array( $batch ) ? $batch : array() as $line ) {
				$product = empty( $line['reference'] ) ? false : UpsellProcessor::find_product_by_reference( $line['reference'] );
				if ( $product ) {
					$excluded_ids[] = $product->get_id();
				}
			}
		}

		return array_filter(
			array_map( 'wc_get_product', array_unique( $linked_ids ) ),
			function ( $product ) use ( $excluded_ids ) {
				return $product && ! in_array( $product->get_id(), $excluded_ids, true ) && $this->can_be_upsold( $product );
			}
		);
	}

	/**
	 * Get the IDs a product links to for a recommendation source.
	 *
	 * @param WC_Product $product The product.
	 * @param string     $source  One of cross_sells, upsells or kustom_upsells.
	 *
	 * @return int[]
	 */
	private static function get_linked_ids( $product, $source ) {
		switch ( $source ) {
			case 'cross_sells':
				return $product->get_cross_sell_ids();
			case 'upsells':
				return $product->get_upsell_ids();
			default:
				return self::get_kustom_upsell_ids( $product );
		}
	}

	/**
	 * Get the Kustom upsell product IDs of a product.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return int[]
	 */
	public static function get_kustom_upsell_ids( $product ) {
		return array_map( 'absint', array_filter( (array) $product->get_meta( self::KUSTOM_UPSELLS_META_KEY ) ) );
	}

	/**
	 * Whether a product can be added to the order as an upsell.
	 *
	 * Only simple products and variations are supported, since Kustom returns a single reference that is added to the order as is.
	 * A variation with an "Any" attribute is skipped, since the customer has no way to pick its value.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return bool
	 */
	private function can_be_upsold( $product ) {
		if ( $product instanceof \WC_Product_Variation && in_array( '', $product->get_variation_attributes(), true ) ) {
			return false;
		}

		return $product->is_type( array( 'simple', 'variation' ) ) && $product->is_purchasable() && $product->is_in_stock() && 'publish' === $product->get_status();
	}

	/**
	 * Format a product as a Kustom upsell line.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return array|null The upsell line, or null if the product costs more than the allowed upsell amount.
	 */
	private function get_upsell_line( $product ) {
		$price     = (float) $product->get_price();
		$tax_rates = $product->is_taxable() ? WC_Tax::get_rates_from_location(
			$product->get_tax_class(),
			array(
				$this->order->get_billing_country(),
				$this->order->get_billing_state(),
				$this->order->get_billing_postcode(),
				$this->order->get_billing_city(),
			)
		) : array();

		if ( wc_prices_include_tax() ) {
			$tax            = array_sum( WC_Tax::calc_inclusive_tax( $price, $tax_rates ) );
			$price_incl_tax = $price;
		} else {
			$tax            = array_sum( WC_Tax::calc_exclusive_tax( $price, $tax_rates ) );
			$price_incl_tax = $price + $tax;
		}

		$unit_price = (int) round( $price_incl_tax * 100 );
		$max_amount = (int) ( $this->request_data['max_upsell_amount'] ?? 0 );
		if ( $unit_price <= 0 || $unit_price > $max_amount ) {
			return null;
		}

		$max_quantity = (int) floor( $max_amount / $unit_price );
		if ( $product->is_sold_individually() ) {
			$max_quantity = 1;
		} elseif ( $product->managing_stock() && ! $product->backorders_allowed() ) {
			$max_quantity = min( $max_quantity, (int) $product->get_stock_quantity() );
		}

		$tax_rate = $price_incl_tax - $tax > 0 ? (int) round( $tax / ( $price_incl_tax - $tax ) * 10000 ) : 0;

		$line = array(
			'type'                 => $product->is_virtual() ? 'digital' : 'physical',
			'reference'            => substr( (string) ( $product->get_sku() ? $product->get_sku() : $product->get_id() ), 0, 64 ),
			'name'                 => substr( $product->get_name(), 0, 255 ),
			'quantity'             => 1,
			'unit_price'           => $unit_price,
			'tax_rate'             => $tax_rate,
			'total_amount'         => $unit_price,
			'total_tax_amount'     => (int) round( $tax * 100 ),
			'max_allowed_quantity' => max( 1, $max_quantity ),
			'product_url'          => $product->get_permalink(),
		);

		$image_url = wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' );
		if ( $image_url ) {
			$line['image_url'] = $image_url;
		}

		$description = wp_strip_all_tags( $product->get_short_description() );
		if ( $description ) {
			$line['description'] = mb_substr( $description, 0, 1024 );
		}

		return $line;
	}
}
