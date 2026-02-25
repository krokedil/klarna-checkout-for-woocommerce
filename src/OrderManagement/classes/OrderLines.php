<?php
/**
 * Order lines formatter
 *
 * Formats WooCommerce cart items for Klarna API.
 *
 * @package OrderManagement
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OrderManagement_Order_Lines class.
 *
 * Processes order lines from a WooCommerce order for Klarna order management requests.
 */
class OrderLines {

	/**
	 * Klarna order order lines.
	 *
	 * @var array
	 */
	public $order_lines = array();

	/**
	 * Klarna order amount.
	 *
	 * @var integer
	 */
	public $order_amount = 0;

	/**
	 * Klarna order tax amount.
	 *
	 * @var integer
	 */
	public $order_tax_amount = 0;

	/**
	 * WooCommerce order ID.
	 *
	 * @var int
	 */
	public $order_id;

	/**
	 * The request type.
	 *
	 * @var string
	 */
	public $request_type;

	/**
	 * WooCommerce order.
	 *
	 * @var bool|WC_Order|WC_Order_Refund
	 */
	public $order;

	/**
	 * Klarna country used for creating this order.
	 *
	 * @var string
	 */
	public $klarna_country = 'US';

	/**
	 * Send sales tax as separate item (US merchants).
	 *
	 * @var bool
	 */
	public $separate_sales_tax = false;

	/**
	 * OrderManagement_Order_Lines constructor.
	 *
	 * @param int    $order_id WooCommerce order ID.
	 * @param string $request_type The request type.
	 */
	public function __construct( $order_id, $request_type = '' ) {
		$this->order_id     = $order_id;
		$this->order        = wc_get_order( $this->order_id );
		$this->request_type = $request_type;

		$base_location = wc_get_base_location();
		$shop_country  = $base_location['country'];

		if ( 'US' === $shop_country ) {
			$this->separate_sales_tax = true;
		}

		$this->klarna_country = strtoupper( $this->order->get_meta( '_wc_klarna_country', true ) );
	}

	/**
	 * Gets formatted order lines from WooCommerce order and returns them, with order amount and order tax amount.
	 *
	 * @return array
	 */
	public function order_lines() {
		// @TODO: Process fees.
		$this->process_order_line_items();
		$this->process_sales_tax();
		return array(
			'order_lines'      => $this->order_lines,
			'order_amount'     => $this->order_amount,
			'order_tax_amount' => $this->order_tax_amount,
		);
	}

	/**
	 * Process WooCommerce order items to Klarna Payments order lines.
	 */
	public function process_order_line_items() {
		$order = wc_get_order( $this->order_id );
		if ( 'capture' !== $this->request_type ) {
			$order->calculate_shipping();
			$order->calculate_taxes();
			$order->calculate_totals();
		}

		// Set order amount from order total.
		$this->order_amount = intval( round( $order->get_total() * 100 ) );

		/**
		 * Process order item products.
		 *
		 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
		 */
		foreach ( $order->get_items() as $order_item ) {
			$klarna_item = $this->process_order_item_product( $order_item, $order );

			$order_line_item = apply_filters( 'kom_wc_order_line_item', $klarna_item, $order_item );
			if ( $order_line_item ) {
				$this->order_lines[] = $order_line_item;
			}
		}

		/**
		 * Process order item shipping.
		 *
		 * @var WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
		 */
		foreach ( $order->get_items( 'shipping' ) as $order_item ) {
			$this->order_lines[] = $this->process_order_item_shipping( $order_item, $order );
		}

		/**
		 * Process order item fee.
		 *
		 * @var WC_Order_Item_Fee $order_item WooCommerce order item fee.
		 */
		foreach ( $order->get_items( 'fee' ) as $order_item ) {
			$this->order_lines[] = $this->process_order_item_fee( $order_item, $order );
		}

		/**
		 * Process order item coupon.
		 *
		 * @var WC_Order_Item_Coupon $order_item WooCommerce order item coupon.
		 */
		foreach ( $order->get_items( 'coupon' ) as $order_item ) {

			/* Only smart coupons are added to the capture order lines, or if the merchant is a Klarna US merchant. */
			$coupon = new WC_Coupon( $order_item->get_name() );
			if ( 'smart_coupon' === $coupon->get_discount_type() || 'US' === $this->klarna_country ) {
				$this->order_lines[] = $this->process_order_item_coupon( $order_item, $order );
			}
		}

		/**
		 * PW WooCommerce Gift Cards.
		 */
		foreach ( $order->get_items( 'pw_gift_card' ) as $gift_card ) {
			$code             = $gift_card->get_card_number();
			$gift_card_sku    = apply_filters( 'klarna_pw_gift_card_sku', __( 'gift_card', 'klarna-checkout-for-woocommerce' ), $code );
			$gift_card_amount = intval( $gift_card->get_amount() * -100 );
			$order_item       = array(
				'type'                  => 'gift_card',
				'reference'             => $gift_card_sku,
				'name'                  => __( 'Gift card', 'pw-woocommerce-gift-cards' ) . ' ' . $code,
				'quantity'              => 1,
				'tax_rate'              => 0,
				'total_discount_amount' => 0,
				'total_tax_amount'      => 0,
				'unit_price'            => $gift_card_amount,
				'total_amount'          => $gift_card_amount,
			);

			$this->order_lines[] = $order_item;
		}

		$added_surcharge = json_decode( $this->order->get_meta( '_kco_added_surcharge', true ), true );

		if ( ! empty( $added_surcharge ) ) {
			$this->order_lines[] = $added_surcharge;
		}
	}

	/**
	 * Process order item product and return it formatted for the request.
	 *
	 * @param WC_Order_Item_Product $order_item WooCommerce order item product.
	 * @param WC_Order              $order The WooCommerce order.
	 * @return array
	 */
	public function process_order_item_product( $order_item, $order ) {
		$order_line = array(
			'reference'             => $this->get_item_reference( $order_item ),
			'type'                  => $this->get_item_type( $order_item ),
			'name'                  => $this->get_item_name( $order_item ),
			'quantity'              => $this->get_item_quantity( $order_item ),
			'unit_price'            => $this->get_item_unit_price( $order_item ),
			'tax_rate'              => $this->get_item_tax_rate( $order, $order_item ),
			'total_amount'          => $this->get_item_total_amount( $order_item ),
			'total_discount_amount' => $this->get_item_discount_amount( $order_item ),
			'total_tax_amount'      => $this->get_item_tax_amount( $order_item ),
		);

		$product_urls = kom_maybe_add_product_urls( $order_item );
		if ( ! empty( $product_urls ) ) {
			$order_line = array_merge( $order_line, $product_urls );
		}

		return $order_line;
	}

	/**
	 * Process order item shipping and return it formatted for the request.
	 *
	 * @param WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
	 * @param WC_Order               $order The WooCommerce order.
	 * @return array
	 */
	public function process_order_item_shipping( $order_item, $order ) {
		$reference = json_decode( $this->order->get_meta( '_kco_kss_data', true ), true );

		return array(
			'reference'             => ( isset( $reference['id'] ) ) ? $reference['id'] : $this->get_item_reference( $order_item ),
			'type'                  => 'shipping_fee',
			'name'                  => $this->get_item_name( $order_item ),
			'quantity'              => 1,
			'unit_price'            => $this->get_item_unit_price( $order_item ),
			'tax_rate'              => $this->get_item_tax_rate( $order, $order_item ),
			'total_amount'          => $this->get_item_total_amount( $order_item ),
			'total_discount_amount' => $this->get_item_discount_amount( $order_item ),
			'total_tax_amount'      => $this->get_item_tax_amount( $order_item ),
		);
	}

	/**
	 * Process order item fee and return it formatted for the request.
	 *
	 * @param WC_Order_Item_Fee $order_item WooCommerce order item fee.
	 * @param WC_Order          $order The WooCommerce order.
	 * @return array
	 */
	public function process_order_item_fee( $order_item, $order ) {
		return array(
			'reference'             => $this->get_item_reference( $order_item ),
			'type'                  => 'surcharge',
			'name'                  => $this->get_item_name( $order_item ),
			'quantity'              => 1,
			'unit_price'            => $this->get_item_unit_price( $order_item ),
			'tax_rate'              => $this->get_item_tax_rate( $order, $order_item ),
			'total_amount'          => $this->get_item_total_amount( $order_item ),
			'total_discount_amount' => $this->get_item_discount_amount( $order_item ),
			'total_tax_amount'      => $this->get_item_tax_amount( $order_item ),
		);
	}

	/**
	 * Process order item coupon and return it formatted for the request.
	 *
	 * @param WC_Order_Item_Coupon $order_item WooCommerce order item coupon.
	 * @param WC_Order             $order The WooCommerce order.
	 * @return array
	 */
	public function process_order_item_coupon( $order_item, $order ) {
		$klarna_item = array(
			'reference'             => $this->get_item_reference( $order_item ),
			'type'                  => 'discount',
			'name'                  => $this->get_item_name( $order_item ),
			'quantity'              => 1,
			'unit_price'            => $this->get_item_unit_price( $order_item ),
			'tax_rate'              => $this->get_item_tax_rate( $order, $order_item ),
			'total_amount'          => $this->get_item_total_amount( $order_item ),
			'total_discount_amount' => 0,
			'total_tax_amount'      => $this->get_item_tax_amount( $order_item ),
		);

		$coupon = new WC_Coupon( $order_item->get_name() );

		// @TODO: For now, only send smart coupons as separate items, needs to include all coupons for US
		if ( 'smart_coupon' === $coupon->get_discount_type() ) {
			$coupon_amount     = - $order_item['discount_amount'] * 100;
			$coupon_tax_amount = - $order_item['discount_amount_tax'] * 100;
			$coupon_reference  = 'Discount';
		} elseif ( 'US' === $this->klarna_country ) {
				$coupon_amount     = 0;
				$coupon_tax_amount = 0;

			if ( $coupon->is_type( 'fixed_cart' ) || $coupon->is_type( 'percent' ) ) {
				$coupon_type = 'Cart discount';
			} elseif ( $coupon->is_type( 'fixed_product' ) || $coupon->is_type( 'percent_product' ) ) {
				$coupon_type = 'Product discount';
			} else {
				$coupon_type = 'Discount';
			}

				$coupon_reference = $coupon_type . ' (amount: ' . $order_item['discount_amount'] . ', tax amount: ' . $order_item['discount_amount_tax'] . ')';
		}

		// Add discount line item, only if it's a smart coupon or purchase country was US.
		if ( 'smart_coupon' === $coupon->get_discount_type() || 'US' === $this->klarna_country ) {
			$klarna_item['type']                  = 'discount';
			$klarna_item['reference']             = $coupon_reference;
			$klarna_item['total_discount_amount'] = 0;
			$klarna_item['unit_price']            = $coupon_amount;
			$klarna_item['total_amount']          = $coupon_amount;
			$klarna_item['total_tax_amount']      = $coupon_tax_amount;

			$this->order_amount     += $coupon_amount;
			$this->order_tax_amount += $coupon_tax_amount;
		}

		return $klarna_item;
	}

	/**
	 * Process sales tax for US
	 */
	public function process_sales_tax() {
		if ( $this->separate_sales_tax ) {
			$sales_tax_amount = round( ( $this->order->get_cart_tax() + $this->order->get_shipping_tax() ) * 100 );

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
			$this->order_amount    += $sales_tax_amount;
			$this->order_tax_amount = $sales_tax_amount;
		}
	}

	/**
	 * Get cart item reference.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item WooCommerce order line item.
	 *
	 * @return string $item_reference Cart item reference.
	 */
	public function get_item_reference( $order_line_item ) {
		if ( 'line_item' === $order_line_item->get_type() ) {
			$product = $order_line_item['variation_id'] ? wc_get_product( $order_line_item['variation_id'] ) : wc_get_product( $order_line_item['product_id'] );
			if ( $product ) {
				if ( $product->get_sku() ) {
					$item_reference = $product->get_sku();
				} else {
					$item_reference = $product->get_id();
				}
			} else {
				$item_reference = $order_line_item->get_name();
			}
		} elseif ( 'shipping' === $order_line_item->get_type() ) {
			// Matching the shipping reference from KCO order.
			$item_reference = $order_line_item->get_method_id() . ':' . $order_line_item->get_instance_id();
		} elseif ( 'coupon' === $order_line_item->get_type() ) {
			$item_reference = 'Discount';
		} elseif ( 'fee' === $order_line_item->get_type() ) {
			$item_reference = 'Fee';
		} else {
			$item_reference = $order_line_item->get_name();
		}

		return substr( (string) $item_reference, 0, 64 );
	}

	/**
	 * Get cart item name.
	 *
	 * @param  WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 *
	 * @return string $order_line_item_name Order line item name.
	 */
	public function get_item_name( $order_line_item ) {
		// Matching the item name from KCO order.
		$order_line_item_name = $order_line_item->get_name();

		return (string) wp_strip_all_tags( $order_line_item_name );
	}

	/**
	 * Get cart item quantity.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 *
	 * @return integer Cart item quantity.
	 */
	public function get_item_quantity( $order_line_item ) {
		if ( $order_line_item->get_quantity() ) {
			return $order_line_item->get_quantity();
		} else {
			return 1;
		}
	}

	/**
	 * Get cart item price.
	 *
	 * @param  WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 *
	 * @return integer $item_price Cart item price.
	 */
	public function get_item_unit_price( $order_line_item ) {
		if ( 'shipping' === $order_line_item->get_type() ) {
			if ( $this->separate_sales_tax ) {
				$item_price = $this->order->get_shipping_total();
			} else {
				$item_price = $this->order->get_shipping_total() + $this->order->get_shipping_tax();
			}

			$item_quantity = 1;
		} elseif ( 'fee' === $order_line_item->get_type() ) {
			if ( $this->separate_sales_tax ) {
				$item_price = $order_line_item->get_total();
			} else {
				$item_price = $order_line_item->get_total() + $order_line_item->get_total_tax();
			}
			$item_quantity = 1;
		} elseif ( 'coupon' === $order_line_item->get_type() ) {
			$item_price    = $order_line_item->get_discount();
			$item_quantity = 1;
		} else {
			if ( $this->separate_sales_tax ) {
				$item_price = $order_line_item->get_subtotal();
			} else {
				$item_price = $order_line_item->get_subtotal() + $order_line_item->get_subtotal_tax();
			}

			$item_quantity = $order_line_item->get_quantity() ? $order_line_item->get_quantity() : 1;
		}

		$item_price = number_format( $item_price * 100, 0, '', '' ) / $item_quantity;

		return round( $item_price );
	}

	/**
	 * Gets the order line tax rate.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param mixed    $order_item If not false the WooCommerce order item WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon.
	 * @return int
	 */
	public function get_item_tax_rate( $order, $order_item = false ) {
		if ( 'coupon' === $order_item->get_type() ) {
			return 0;
		}
		$tax_items = $order->get_items( 'tax' );
		/**
		 * Loop through the WooCommerce tax items.
		 *
		 * @var WC_Order_Item_Tax $tax_item The WooCommerce tax item.
		 */
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			foreach ( $order_item->get_taxes()['total'] as $key => $value ) {
				if ( '' !== $value ) {
					if ( $rate_id === $key ) {
						return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] * 100 );
					}
				}
			}
		}

		// If we get here, there is no tax set for the order item.
		return 0;
	}


	/**
	 * Get order line item total amount.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 *
	 * @return integer $item_total_amount Cart item total amount.
	 */
	public function get_item_total_amount( $order_line_item ) {
		if ( 'shipping' === $order_line_item->get_type() ) {
			if ( $this->separate_sales_tax ) {
				$item_total_amount = $this->order->get_shipping_total();
			} else {
				$item_total_amount = $this->order->get_shipping_total() + (float) $this->order->get_shipping_tax();
			}
		} elseif ( 'fee' === $order_line_item->get_type() ) {
			if ( $this->separate_sales_tax ) {
				$item_total_amount = $order_line_item->get_total();
			} else {
				$item_total_amount = $order_line_item->get_total() + $order_line_item->get_total_tax();
			}
		} elseif ( 'coupon' === $order_line_item->get_type() ) {
			$item_total_amount = $order_line_item->get_discount();
		} elseif ( $this->separate_sales_tax ) {
				$item_total_amount = $order_line_item->get_subtotal();
		} else {
			$item_total_amount = $order_line_item->get_total() + $order_line_item->get_total_tax();
		}

		$item_total_amount = $item_total_amount * 100;

		return round( $item_total_amount );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @param  WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public function get_item_tax_amount( $order_line_item ) {
		if ( $this->separate_sales_tax ) {
			$item_tax_amount = 00;
		} elseif ( in_array( $order_line_item->get_type(), array( 'line_item', 'fee', 'shipping' ), true ) ) {
				$item_tax_amount = $order_line_item->get_total_tax() * 100;
		} elseif ( 'coupon' === $order_line_item->get_type() ) {
			$item_tax_amount = $order_line_item->get_discount_tax() * 100;
		} else {
			$item_tax_amount = 00;
		}

		return round( $item_tax_amount );
	}

	/**
	 * Get cart item discount.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 *
	 * @return integer $item_discount_amount Cart item discount.
	 */
	public function get_item_discount_amount( $order_line_item ) {
		if ( $order_line_item['subtotal'] > $order_line_item['total'] ) {
			if ( $this->separate_sales_tax ) {
				$item_discount_amount = ( $order_line_item['subtotal'] - $order_line_item['total'] ) * 100;
			} else {
				$item_discount_amount = ( $order_line_item['subtotal'] + $order_line_item['subtotal_tax'] - $order_line_item['total'] - $order_line_item['total_tax'] ) * 100;
			}
		} else {
			$item_discount_amount = 0;
		}

		return round( $item_discount_amount );
	}

	/**
	 * Get cart item discount.
	 *
	 * @param WC_Order_Item_Product $order_line_item Order line item.
	 *
	 * @return string $item_type Order item type.
	 */
	public function get_item_type( $order_line_item ) {
		$product = $order_line_item->get_product();
		return $product && ! $product->is_virtual() ? 'physical' : 'digital';
	}
}
