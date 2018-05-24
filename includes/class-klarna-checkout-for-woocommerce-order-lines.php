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
	public function __construct( $shop_country = null ) {
		if ( ! $shop_country ) {
			$base_location = wc_get_base_location();
			$shop_country  = $base_location['country'];
		}

		$this->shop_country = $shop_country;

		if ( 'US' === $this->shop_country ) {
			$this->separate_sales_tax = true;
		}
	}

	/**
	 * Processes cart data
	 */
	public function process_data() {
		// @TODO: Process fees
		$this->process_cart();
		$this->process_shipping();
		$this->process_sales_tax();
		$this->process_coupons();
		$this->process_fees();
	}

	/**
	 * Gets formatted order lines from WooCommerce cart.
	 *
	 * @return array
	 */
	public function get_order_lines() {
		return $this->order_lines;
	}

	/**
	 * Gets order amount for Klarna API.
	 *
	 * @return int
	 */
	public function get_order_amount() {
		return round( WC()->cart->total * 100 );
	}

	/**
	 * Gets order tax amount for Klarna API.
	 *
	 * @return int
	 */
	public function get_order_tax_amount() {
		return round( ( WC()->cart->tax_total + WC()->cart->shipping_tax_total ) * 100 );
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

				// Add images.
				$klarna_checkout_settings = get_option( 'woocommerce_kco_settings' );
				if ( array_key_exists( 'send_product_urls', $klarna_checkout_settings ) && 'yes' === $klarna_checkout_settings['send_product_urls'] ) {
					$klarna_item['product_url'] = $this->get_item_product_url( $product );
					if ( $this->get_item_image_url( $product ) ) {
						$klarna_item['image_url'] = $this->get_item_image_url( $product );
					}
				}

				$this->order_lines[] = $klarna_item;
			}
		}
	}

	/**
	 * Process WooCommerce shipping to Klarna Payments order lines.
	 */
	public function process_shipping() {
		if ( WC()->shipping->get_packages() && WC()->session->get( 'chosen_shipping_methods' )[0] ) {
			$shipping            = array(
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
		}
	}

	/**
	 * Process sales tax for US.
	 */
	public function process_sales_tax() {
		if ( $this->separate_sales_tax ) {
			$sales_tax_amount = round( ( WC()->cart->tax_total + WC()->cart->shipping_tax_total ) * 100 );
			// Add sales tax line item.
			$sales_tax           = array(
				'type'                  => 'sales_tax',
				'reference'             => __( 'Sales Tax', 'klarna-checkout-for-woocommerce' ),
				'name'                  => __( 'Sales Tax', 'klarna-checkout-for-woocommerce' ),
				'quantity'              => 1,
				'unit_price'            => $sales_tax_amount,
				'tax_rate'              => 0,
				'total_amount'          => $sales_tax_amount,
				'total_discount_amount' => 0,
				'total_tax_amount'      => 0,
			);
			$this->order_lines[] = $sales_tax;
		}
	}

	/**
	 * Process smart coupons.
	 */
	public function process_coupons() {
		if ( ! empty( WC()->cart->get_coupons() ) ) {
			foreach ( WC()->cart->get_coupons() as $coupon_key => $coupon ) {
				$coupon_reference  = '';
				$coupon_amount     = 0;
				$coupon_tax_amount = '';
				// Smart coupons are processed as real line items, cart and product discounts sent for reference only.
				if ( 'smart_coupon' === $coupon->get_discount_type() ) {
					$coupon_amount     = - WC()->cart->get_coupon_discount_amount( $coupon_key ) * 100;
					$coupon_tax_amount = - WC()->cart->get_coupon_discount_tax_amount( $coupon_key ) * 100;
					$coupon_reference  = 'Discount';
				} else {
					if ( 'US' === $this->shop_country ) {
						$coupon_amount     = 0;
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
				}
				// Add separate discount line item, but only if it's a smart coupon or country is US.
				if ( 'US' === $this->shop_country || 'smart_coupon' === $coupon->get_discount_type() ) {
					$discount            = array(
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
					$this->order_lines[] = $discount;
				}
			} // End foreach().
		} // End if().
	}

	/**
	 * Process cart fees.
	 */
	public function process_fees() {
		if ( ! empty( WC()->cart->get_fees() ) ) {
			foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
				if ( $this->separate_sales_tax ) {
					$fee_tax_rate   = 0;
					$fee_tax_amount = 0;
					$fee_amount     = round( $fee->amount * 100 );
				} else {
					$fee_tax_amount = round( $fee->tax * 100 );
					$fee_amount     = round( ( $fee->amount + $fee->tax ) * 100 );

					$_tax      = new WC_Tax();
					$tmp_rates = $_tax->get_rates( $fee->tax_class );
					$vat       = array_shift( $tmp_rates );

					if ( isset( $vat['rate'] ) ) {
						$fee_tax_rate = round( $vat['rate'] * 100 );
					} else {
						$fee_tax_rate = 0;
					}
				}

				// Add separate discount line item, but only if it's a smart coupon or country is US.
				$fee_item = array(
					'type'                  => 'shipping_fee',
					'reference'             => $fee->id,
					'name'                  => $fee->name,
					'quantity'              => 1,
					'unit_price'            => $fee_amount,
					'tax_rate'              => $fee_tax_rate,
					'total_amount'          => $fee_amount,
					'total_discount_amount' => 0,
					'total_tax_amount'      => $fee_tax_amount,
				);

				$this->order_lines[] = $fee_item;
			} // End foreach().
		} // End if().
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
		$item_name      = $cart_item_data->get_name();

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
			$item_tax_amount = 0;
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
	 * @param  object $product   Product object.
	 *
	 * @return integer $item_tax_rate Item tax percentage formatted for Klarna.
	 */
	public function get_item_tax_rate( $cart_item, $product ) {
		if ( $product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
			// Calculate tax rate.
			if ( $this->separate_sales_tax ) {
				$item_tax_rate = 0;
			} else {
				$_tax      = new WC_Tax();
				$tmp_rates = $_tax->get_rates( $product->get_tax_class() );
				$vat       = array_shift( $tmp_rates );
				if ( isset( $vat['rate'] ) ) {
					$item_tax_rate = round( $vat['rate'] * 100 );
				} else {
					$item_tax_rate = 0;
				}
			}
		} else {
			$item_tax_rate = 0;
		}

		return round( $item_tax_rate );
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
		$item_price = $item_subtotal * 100 / $cart_item['quantity'];

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
		return round( $cart_item['quantity'] );
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
		} else {
			$item_reference = $product->get_id();
		}

		return substr( (string) $item_reference, 0, 64 );
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
				$item_discount_amount = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] - $cart_item['line_total'] - $cart_item['line_tax'];
			}
			$item_discount_amount = $item_discount_amount * 100;
		} else {
			$item_discount_amount = 0;
		}

		return round( $item_discount_amount );
	}

	/**
	 * Get cart item product URL.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @param  WC_Product $product Product.
	 *
	 * @return string $item_product_url Cart item product URL.
	 */
	public function get_item_product_url( $product ) {
		return $product->get_permalink();
	}

	/**
	 * Get cart item product image URL.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @param  WC_Product $product Product.
	 *
	 * @return string $item_product_image_url Cart item product image URL.
	 */
	public function get_item_image_url( $product ) {
		$image_url = false;
		if ( $product->get_image_id() > 0 ) {
			$image_id  = $product->get_image_id();
			$image_url = wp_get_attachment_image_url( $image_id, 'shop_thumbnail', false );
		}

		return $image_url;
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
		$item_discount_rate = ( 1 - ( $cart_item['line_total'] / $cart_item['line_subtotal'] ) ) * 100 * 100;

		return round( $item_discount_rate );
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
			$shipping_name = __( 'Shipping', 'klarna-checkout-for-woocommerce' );
		}

		return (string) $shipping_name;
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
			$shipping_reference = __( 'Shipping', 'klarna-checkout-for-woocommerce' );
		}

		return (string) $shipping_reference;
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

		return $shipping_amount;
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
			$shipping_tax_rate = round( WC()->cart->shipping_tax_total / WC()->cart->shipping_total, 2 ) * 100 * 100;
		} else {
			$shipping_tax_rate = 0;
		}

		return round( $shipping_tax_rate );
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

		return round( $shipping_tax_amount );
	}

}