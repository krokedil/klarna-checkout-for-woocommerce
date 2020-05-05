<?php // phpcs:ignore
/**
 *
 * Undocumented class
 *
 * @package category
 */
/**
 * Undocumented class
 */
class Test_KCO_Cart_Helpers extends AKrokedil_Unit_Test_Case {

	public $simple_products = array();

	public function test_get_order_amount_25_inc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 10000, $order_amount );
	}

	public function test_get_order_amount_12_inc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 10000, $order_amount );
	}

	public function test_get_order_amount_6_inc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 10000, $order_amount );
	}

	public function test_get_order_amount_0_inc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 10000, $order_amount );
	}

	public function test_get_order_amount_25_exc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 12500, $order_amount );
	}

	public function test_get_order_amount_12_exc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 11200, $order_amount );
	}

	public function test_get_order_amount_6_exc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 10600, $order_amount );
	}

	public function test_get_order_amount_0_exc_tax() {
		$order_amount = ( new KCO_Request_Cart() )->get_order_amount();
		$this->assertEquals( 10000, $order_amount );
	}

	public function test_get_order_tax_amount_25_inc() {
		$helper = new KCO_Request_Cart();
		$helper->process_data();
		$order_lines  = $helper->get_order_lines();
		$order_amount = $helper->get_order_tax_amount( $order_lines );
		$this->assertEquals( 2000, $order_amount );
	}

	public function test_get_order_tax_amount_12_inc() {
		$helper = new KCO_Request_Cart();
		$helper->process_data();
		$order_lines  = $helper->get_order_lines();
		$order_amount = $helper->get_order_tax_amount( $order_lines );
		$this->assertEquals( 1071, $order_amount );
	}

	public function test_get_order_tax_amount_6_inc() {
		$helper = new KCO_Request_Cart();
		$helper->process_data();
		$order_lines  = $helper->get_order_lines();
		$order_amount = $helper->get_order_tax_amount( $order_lines );
		$this->assertEquals( 566, $order_amount );
	}

	public function test_get_order_tax_amount_0_inc() {
		$helper = new KCO_Request_Cart();
		$helper->process_data();
		$order_lines  = $helper->get_order_lines();
		$order_amount = $helper->get_order_tax_amount( $order_lines );
		$this->assertEquals( 0, $order_amount );
	}

	public function test_get_item_name() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$name = ( new KCO_Request_Cart() )->get_item_name( $cart_item );
			$this->assertEquals( 'Default product name', $name );
		}
	}

	public function test_get_item_quantity() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_quantity( $cart_item );
			$this->assertEquals( 1, $quantity );
		}
	}

	public function test_get_item_unit_price_25_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_unit_price_12_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_unit_price_6_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_unit_price_0_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_unit_price_25_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 12500, $quantity );
		}
	}

	public function test_get_item_unit_price_12_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 11200, $quantity );
		}
	}

	public function test_get_item_unit_price_6_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 10600, $quantity );
		}
	}

	public function test_get_item_unit_price_0_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_tax_rate_25_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 2500, $quantity );
		}
	}

	public function test_get_item_tax_rate_12_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 1200, $quantity );
		}
	}

	public function test_get_item_tax_rate_6_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 600, $quantity );
		}
	}

	public function test_get_item_tax_rate_0_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 0, $quantity );
		}
	}

	public function test_get_item_tax_rate_25_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 2500, $quantity );
		}
	}

	public function test_get_item_tax_rate_12_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 1200, $quantity );
		}
	}

	public function test_get_item_tax_rate_6_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 600, $quantity );
		}
	}

	public function test_get_item_tax_rate_0_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
			$this->assertEquals( 0, $quantity );
		}
	}

	public function test_get_item_total_amount_25_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_total_amount_12_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_total_amount_6_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_total_amount_0_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_total_amount_25_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 12500, $quantity );
		}
	}

	public function test_get_item_total_amount_12_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 11200, $quantity );
		}
	}

	public function test_get_item_total_amount_6_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 10600, $quantity );
		}
	}

	public function test_get_item_total_amount_0_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
			$this->assertEquals( 10000, $quantity );
		}
	}

	public function test_get_item_tax_amount_25_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 2000, $quantity );
		}
	}

	public function test_get_item_tax_amount_12_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 1071, $quantity );
		}
	}

	public function test_get_item_tax_amount_6_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 566, $quantity );
		}
	}

	public function test_get_item_tax_amount_0_inc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 0, $quantity );
		}
	}

	public function test_get_item_tax_amount_25_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 2500, $quantity );
		}
	}

	public function test_get_item_tax_amount_12_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 1200, $quantity );
		}
	}

	public function test_get_item_tax_amount_6_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 600, $quantity );
		}
	}

	public function test_get_item_tax_amount_0_exc() {
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			if ( $cart_item['variation_id'] ) {
				$product = wc_get_product( $cart_item['variation_id'] );
			} else {
				$product = wc_get_product( $cart_item['product_id'] );
			}
			$quantity = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
			$this->assertEquals( 0, $quantity );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function create() {
		$settings = array(
			'enabled'                           => 'yes',
			'title'                             => 'Klarna',
			'description'                       => '',
			'select_another_method_text'        => '',
			'testmode'                          => 'yes',
			'logging'                           => 'yes',
			'credentials_eu'                    => '',
			'merchant_id_eu'                    => '',
			'shared_secret_eu'                  => '',
			'test_merchant_id_eu'               => '',
			'test_shared_secret_eu'             => '',
			'credentials_us'                    => '',
			'merchant_id_us'                    => '',
			'shared_secret_us'                  => '',
			'test_merchant_id_us'               => '',
			'test_shared_secret_us'             => '',
			'shipping_section'                  => '',
			'allow_separate_shipping'           => 'no',
			'shipping_methods_in_iframe'        => 'no',
			'shipping_details'                  => '',
			'checkout_section'                  => '',
			'send_product_urls'                 => 'yes',
			'dob_mandatory'                     => 'no',
			'display_privacy_policy_text'       => 'no',
			'add_terms_and_conditions_checkbox' => 'no',
			'allowed_customer_types'            => 'B2C',
			'title_mandatory'                   => 'yes',
			'prefill_consent'                   => 'yes',
			'quantity_fields'                   => 'no',
			'color_settings_title'              => '',
			'color_button'                      => '',
			'color_button_text'                 => '',
			'color_checkbox'                    => '',
			'color_checkbox_checkmark'          => '',
			'color_header'                      => '',
			'color_link'                        => '',
			'radius_border'                     => '',
		);

		if ( stristr( $this->getName(), 'inc' ) ) {
			update_option( 'woocommerce_prices_include_tax', 'yes' );
		}

		if ( stristr( $this->getName(), 'exc' ) ) {
			update_option( 'woocommerce_prices_include_tax', 'no' );
		}

		if ( stristr( $this->getName(), '25' ) ) {
			$this->tax_rate_id = $this->create_tax_rate( '25' );
		}

		if ( stristr( $this->getName(), '12' ) ) {
			$this->tax_rate_id = $this->create_tax_rate( '12' );
		}

		if ( stristr( $this->getName(), '6' ) ) {
			$this->tax_rate_id = $this->create_tax_rate( '6' );
		}

		if ( stristr( $this->getName(), '0' ) ) {
			$this->tax_rate_id = $this->create_tax_rate( '0' );
		}

		// Default settings.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_kco_settings', $settings );
		update_option( 'woocommerce_currency', 'SEK' );
		update_option( 'woocommerce_allowed_countries', 'specific' );
		update_option( 'woocommerce_specific_allowed_countries', array( 'SE' ) );

		$this->simple_products[] = array(
			'product'  => ( new Krokedil_Simple_Product() )->create(),
			'quantity' => 1,
		);

		// Create cart.
		WC()->customer->set_billing_country( 'SE' );
		foreach ( $this->simple_products as $simple_product ) {
			WC()->cart->add_to_cart( $simple_product['product']->get_id(), $simple_product['quantity'] );
		}
		WC()->cart->calculate_totals();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function update() {
		return;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function view() {
		return;
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function delete() {
		WC()->cart->empty_cart();
		foreach ( $this->simple_products as $simple_product ) {
			$simple_product['product']->delete();
		}
		$this->simple_products = array();
		if ( $this->tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $this->tax_rate_id );
			$this->tax_rate_id = null;
		}
	}

	public function add_default_products() {
		// Create products.
		$simple_products    = array();
		$simple_products[6] = ( new Krokedil_Simple_Product( array( 'regular_price' => 19.99 ) ) )->create();
		$simple_products[4] = ( new Krokedil_Simple_Product( array( 'regular_price' => 12.77 ) ) )->create();
		$simple_products[1] = ( new Krokedil_Simple_Product( array( 'regular_price' => 6.66 ) ) )->create();
		$simple_products[2] = ( new Krokedil_Simple_Product( array( 'regular_price' => 18.42 ) ) )->create();

		$this->simple_products = $simple_products;
	}

	public function create_tax_rate( $rate ) {
		$tax_data = array(
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => $rate,
			'tax_rate_name'     => 'Vat',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => '',
		);
		return WC_Tax::_insert_tax_rate( $tax_data );
	}
}
