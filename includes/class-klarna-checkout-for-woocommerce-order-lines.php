<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_Order_Lines class.
 *
 * Class that formats WooCommerce cart contents for Klarna API.
 */
class Klarna_Checkout_For_WooCommerce_Order_Lines {

	/**
	 * Formatted order lines.
	 *
	 * @var $order_lines
	 */
	public $order_lines = array();
	/**
	 * Formatted order lines.
	 *
	 * @var $order_lines
	 */
	public $order_amount = 0;
	/**
	 * Formatted order lines.
	 *
	 * @var $order_lines
	 */
	public $order_tax_amount = 0;
	/**
	 * Shop country.
	 *
	 * @var string
	 */
	public $shop_country;
	/**
	 * Send sales tax as separate item (US merchants).
	 *
	 * @var bool
	 */
	public $separate_sales_tax = false;
	/**
	 * WC_Klarna_Payments_Order_Lines constructor.
	 *
	 * @param bool|string $shop_country Shop country.
	 */
	public function __construct( $shop_country = false ) {
		$this->shop_country = $shop_country ? $shop_country : 'US';
		if ( 'US' === $this->shop_country ) {
			$this->separate_sales_tax = true;
		}
	}
	/**
	 * Gets formatted order lines from WooCommerce cart.
	 *
	 * @return array
	 */
	public function order_lines() {
		// @TODO: Process fees
		$this->process_cart();
		$this->process_shipping();
		$this->process_sales_tax();
		$this->process_coupons();
		return array(
			'order_lines' => $this->order_lines,
			'order_amount' => $this->order_amount,
			'order_tax_amount' => $this->order_tax_amount,
		);
	}
	/**
	 * Process WooCommerce cart to Klarna Payments order lines.
	 */
	public function process_cart() {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $cart_item['quantity'] ) {
				if ( $cart_item['variation_id'] ) {
					$product = wc_get_product( $cart_item['variation_id'] );
				} else {
					$product = wc_get_product( $cart_item['product_id'] );
				}
				$klarna_item = array(
					'reference'             => $this->get_item_reference( $product ),
					'name'                  => $this->get_item_name( $cart_item ),
					'quantity'              => $this->get_item_quantity( $cart_item ),
					'unit_price'            => $this->get_item_price( $cart_item ),
					'tax_rate'              => $this->get_item_tax_rate( $cart_item, $product ),
					'total_amount'          => $this->get_item_total_amount( $cart_item ),
					'total_tax_amount'      => $this->get_item_tax_amount( $cart_item ),
					'total_discount_amount' => $this->get_item_discount_amount( $cart_item ),
				);
				$this->order_lines[] = $klarna_item;
				$this->order_amount += $this->get_item_quantity( $cart_item ) * $this->get_item_price( $cart_item ) - $this->get_item_discount_amount( $cart_item );
			}
		}
	}
	/**
	 * Process WooCommerce shipping to Klarna Payments order lines.
	 */
	public function process_shipping() {
		if ( WC()->shipping->get_packages() && WC()->session->get( 'chosen_shipping_methods' ) ) {
			$shipping = array(
				'type'             => 'shipping_fee',
				'reference'        => $this->get_shipping_reference(),
				'name'             => $this->get_shipping_name(),
				'quantity'         => 1,
				'unit_price'       => $this->get_shipping_amount(),
				'tax_rate'         => $this->get_shipping_tax_rate(),
				'total_amount'     => $this->get_shipping_amount(),
				'total_tax_amount' => $this->get_shipping_tax_amount(),
			);
			$this->order_lines[] = $shipping;
			$this->order_amount += $this->get_shipping_amount();
		}
	}
	/**
	 * Process sales tax for US.
	 */
	public function process_sales_tax() {
		if ( $this->separate_sales_tax ) {
			$sales_tax_amount = round( ( WC()->cart->tax_total + WC()->cart->shipping_tax_total ) * 100 );
			// Add sales tax line item.
			$sales_tax = array(
				'type'                  => 'sales_tax',
				'reference'             => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'name'                  => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'quantity'              => 1,
				'unit_price'            => $sales_tax_amount,
				'tax_rate'              => 0,
				'total_amount'          => $sales_tax_amount,
				'total_discount_amount' => 0,
				'total_tax_amount'      => 0,
			);
			$this->order_lines[]    = $sales_tax;
			$this->order_tax_amount = $sales_tax_amount;
			$this->order_amount    += $sales_tax_amount;
		}
	}
	/**
	 * Process smart coupons.
	 */
	public function process_coupons() {
		if ( ! empty( WC()->cart->get_coupons() ) ) {
			foreach ( WC()->cart->get_coupons() as $coupon_key => $coupon ) {
				// Smart coupons are processed as real line items, cart and product discounts sent for reference only.
				if ( 'smart_coupon' === $coupon->discount_type ) {
					$coupon_amount = - WC()->cart->get_coupon_discount_amount( $coupon_key ) * 100;
					$coupon_tax_amount = - WC()->cart->get_coupon_discount_tax_amount( $coupon_key ) * 100;
					$coupon_reference = 'Discount';
				} else {
					$coupon_amount = 0;
					$coupon_tax_amount = 0;
					if ( $coupon->is_type( 'fixed_cart' ) || $coupon->is_type( 'percent' ) ) {
						$coupon_type = 'Cart discount';
					} elseif ( $coupon->is_type( 'fixed_product' ) || $coupon->is_type( 'percent_product' ) ) {
						$coupon_type = 'Product discount';
					} else {
						$coupon_type = 'Discount';
					}
					$coupon_reference = $coupon_type . ' (amount: ' . WC()->cart->get_coupon_discount_amount( $coupon_key ) . ', tax amount: ' . WC()->cart->get_coupon_discount_tax_amount( $coupon_key ) . ')';
				}
				// Add discount line item.
				$discount = array(
					'type'                  => 'discount',
					'reference'             => $coupon_reference,
					'name'                  => $coupon_key,
					'quantity'              => 1,
					'unit_price'            => $coupon_amount,
					'tax_rate'              => 0,
					'total_amount'          => $coupon_amount,
					'total_discount_amount' => 0,
					'total_tax_amount'      => $coupon_tax_amount,
				);
				$this->order_lines[]     = $discount;
				$this->order_tax_amount += $coupon_tax_amount;
				$this->order_amount     += $coupon_amount;
			}
		}
	}
	// Helpers.
	/**
	 * Get cart item name.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return string $item_name Cart item name.
	 */
	public function get_item_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$item_name      = $cart_item_data->post->post_title;
		// Get variations as a string and remove line breaks.
		$item_variations = rtrim( WC()->cart->get_item_data( $cart_item, true ) ); // Removes new line at the end.
		$item_variations = str_replace( "\n", ', ', $item_variations ); // Replaces all other line breaks with commas.
		// Add variations to name.
		if ( '' !== $item_variations ) {
			$item_name .= ' [' . $item_variations . ']';
		}
		return strip_tags( $item_name );
	}
	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public function get_item_tax_amount( $cart_item ) {
		if ( $this->separate_sales_tax ) {
			$item_tax_amount = 00;
		} else {
			$item_tax_amount = $cart_item['line_tax'] * 100;
		}
		return round( $item_tax_amount );
	}
	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array  $cart_item Cart item.
	 * @param  object $product Product object.
	 *
	 * @return integer $item_tax_rate Item tax percentage formatted for Klarna.
	 */
	public function get_item_tax_rate( $cart_item, $product ) {
		// We manually calculate the tax percentage here.
		if ( $product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
			// Calculate tax rate.
			if ( $this->separate_sales_tax ) {
				$item_tax_rate = 00;
			} else {
				$item_tax_rate = round( $cart_item['line_subtotal_tax'] / $cart_item['line_subtotal'] * 100 * 100 );
			}
		} else {
			$item_tax_rate = 00;
		}
		return intval( $item_tax_rate );
	}
	/**
	 * Get cart item price.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_price Cart item price.
	 */
	public function get_item_price( $cart_item ) {
		if ( $this->separate_sales_tax ) {
			$item_subtotal = $cart_item['line_subtotal'];
		} else {
			$item_subtotal = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
		}
		$item_price = number_format( $item_subtotal * 100, 0, '', '' ) / $cart_item['quantity'];
		return round( $item_price );
	}
	/**
	 * Get cart item quantity.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_quantity Cart item quantity.
	 */
	public function get_item_quantity( $cart_item ) {
		return (int) $cart_item['quantity'];
	}
	/**
	 * Get cart item reference.
	 *
	 * Returns SKU or product ID.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  object $product Product object.
	 *
	 * @return string $item_reference Cart item reference.
	 */
	public function get_item_reference( $product ) {
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} elseif ( $product->variation_id ) {
			$item_reference = $product->variation_id;
		} else {
			$item_reference = $product->id;
		}
		return strval( $item_reference );
	}
	/**
	 * Get cart item discount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_discount_amount Cart item discount.
	 */
	public function get_item_discount_amount( $cart_item ) {
		if ( $cart_item['line_subtotal'] > $cart_item['line_total'] ) {
			if ( $this->separate_sales_tax ) {
				$item_discount_amount = $cart_item['line_subtotal'] - $cart_item['line_total'];
			} else {
				$item_discount_amount = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] - $cart_item['line_total'] - $cart_item['line_total_tax'];
			}
		} else {
			$item_discount_amount = 0;
		}
		return round( $item_discount_amount * 100 );
	}
	/**
	 * Get cart item discount rate.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_discount_rate Cart item discount rate.
	 */
	public function get_item_discount_rate( $cart_item ) {
		$item_discount_rate = ( 1 - ( $cart_item['line_total'] / $cart_item['line_subtotal'] ) ) * 10000;
		return (int) round( $item_discount_rate );
	}
	/**
	 * Get cart item total amount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_total_amount Cart item total amount.
	 */
	public function get_item_total_amount( $cart_item ) {
		if ( $this->separate_sales_tax ) {
			$item_total_amount = ( $cart_item['line_total'] * 100 );
		} else {
			$item_total_amount = ( ( $cart_item['line_total'] + $cart_item['line_tax'] ) * 100 );
		}
		return round( $item_total_amount );
	}
	/**
	 * Get shipping method name.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return string $shipping_name Name for selected shipping method.
	 */
	public function get_shipping_name() {
		$shipping_packages = WC()->shipping->get_packages();
		foreach ( $shipping_packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {
						$shipping_name = $rate_value->label;
					}
				}
			}
		}
		if ( ! isset( $shipping_name ) ) {
			$shipping_name = __( 'Shipping', 'woocommerce-gateway-klarna' );
		}
		return $shipping_name;
	}
	/**
	 * Get shipping reference.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return string $shipping_reference Reference for selected shipping method.
	 */
	public function get_shipping_reference() {
		$shipping_packages = WC()->shipping->get_packages();
		foreach ( $shipping_packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {
						$shipping_reference = $rate_value->id;
					}
				}
			}
		}
		if ( ! isset( $shipping_reference ) ) {
			$shipping_reference = __( 'Shipping', 'woocommerce-gateway-klarna' );
		}
		return strval( $shipping_reference );
	}
	/**
	 * Get shipping method amount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return integer $shipping_amount Amount for selected shipping method.
	 */
	public function get_shipping_amount() {
		if ( $this->separate_sales_tax ) {
			$shipping_amount = (int) number_format( WC()->cart->shipping_total * 100, 0, '', '' );
		} else {
			$shipping_amount = (int) number_format( ( WC()->cart->shipping_total + WC()->cart->shipping_tax_total ) * 100, 0, '', '' );
		}
		return (int) $shipping_amount;
	}
	/**
	 * Get shipping method tax rate.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return integer $shipping_tax_rate Tax rate for selected shipping method.
	 */
	public function get_shipping_tax_rate() {
		if ( WC()->cart->shipping_tax_total > 0 && ! $this->separate_sales_tax ) {
			$shipping_tax_rate = round( WC()->cart->shipping_tax_total / WC()->cart->shipping_total, 2 ) * 100;
		} else {
			$shipping_tax_rate = 00;
		}
		return intval( $shipping_tax_rate . '00' );
	}
	/**
	 * Get shipping method tax amount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return integer $shipping_tax_amount Tax amount for selected shipping method.
	 */
	public function get_shipping_tax_amount() {
		if ( $this->separate_sales_tax ) {
			$shipping_tax_amount = 0;
		} else {
			$shipping_tax_amount = WC()->cart->shipping_tax_total * 100;
		}
		return (int) $shipping_tax_amount;
	}

}