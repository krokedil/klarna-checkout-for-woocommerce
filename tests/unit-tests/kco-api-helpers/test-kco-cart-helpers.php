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

	/**
	 * WooCommerce simple product.
	 *
	 * @var WC_Product
	 */
	public $simple_product = null;

	/**
	 * Tax rate ids.
	 *
	 * @var array
	 */
	public $tax_rate_ids = array();

	/**
	 * Test KCO_Request_Cart::get_order_amount
	 *
	 * @return void
	 */
	public function test_get_order_amount() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25' );
		$order_amount_25_inc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12' );
		$order_amount_12_inc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6' );
		$order_amount_6_inc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0' );
		$order_amount_0_inc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// Exclusive tax.
		update_option( 'woocommerce_prices_include_tax', 'no' );

		// 25% exc tax.
		$this->settup_cart( '25' );
		$order_amount_25_exc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12' );
		$order_amount_12_exc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6' );
		$order_amount_6_exc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0' );
		$order_amount_0_exc = ( new KCO_Request_Cart() )->get_order_amount();
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 10000, $order_amount_25_inc, 'get_order_amount 25% inc tax' );
		$this->assertEquals( 10000, $order_amount_12_inc, 'get_order_amount 12% inc tax' );
		$this->assertEquals( 10000, $order_amount_6_inc, 'get_order_amount 6% inc tax' );
		$this->assertEquals( 10000, $order_amount_0_inc, 'get_order_amount 0% inc tax' );
		$this->assertEquals( 12500, $order_amount_25_exc, 'get_order_amount 25% exc tax' );
		$this->assertEquals( 11200, $order_amount_12_exc, 'get_order_amount 12% exc tax' );
		$this->assertEquals( 10600, $order_amount_6_exc, 'get_order_amount 6% exc tax' );
		$this->assertEquals( 10000, $order_amount_0_exc, 'get_order_amount 0% exc tax' );
	}

	/**
	 * Test KCO_Request_Cart::get_order_tax_amount
	 *
	 * @return void
	 */
	public function test_get_order_tax_amount() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25' );
		$order_tax_amount_25_inc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12' );
		$order_tax_amount_12_inc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6' );
		$order_tax_amount_6_inc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0' );
		$order_tax_amount_0_inc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% inc tax.
		$this->settup_cart( '25' );
		$order_tax_amount_25_exc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12' );
		$order_tax_amount_12_exc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6' );
		$order_tax_amount_6_exc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0' );
		$order_tax_amount_0_exc = $this->get_order_tax_amount();
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 2000, $order_tax_amount_25_inc, 'get_order_tax_amount 25% inc tax' );
		$this->assertEquals( 1071, $order_tax_amount_12_inc, 'get_order_tax_amount 12% inc tax' );
		$this->assertEquals( 566, $order_tax_amount_6_inc, 'get_order_tax_amount 6% inc tax' );
		$this->assertEquals( 0, $order_tax_amount_0_inc, 'get_order_tax_amount 0% inc tax' );
		$this->assertEquals( 2500, $order_tax_amount_25_exc, 'get_order_tax_amount 25% exc tax' );
		$this->assertEquals( 1200, $order_tax_amount_12_exc, 'get_order_tax_amount 12% exc tax' );
		$this->assertEquals( 600, $order_tax_amount_6_exc, 'get_order_tax_amount 6% exc tax' );
		$this->assertEquals( 0, $order_tax_amount_0_exc, 'get_order_tax_amount 0% exc tax' );
	}

	/**
	 * Test KCO_Request_Cart::get_item_name
	 *
	 * @return void
	 */
	public function test_get_item_name() {
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$name = ( new KCO_Request_Cart() )->get_item_name( $cart_item );
			$this->assertEquals( 'Default product name', $name );
		}
		WC()->cart->empty_cart();
	}

	/**
	 * Test KCO_Request_Cart::get_item_quantity
	 *
	 * @return void
	 */
	public function test_get_item_quantity() {
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$quantity = ( new KCO_Request_Cart() )->get_item_quantity( $cart_item );
			$this->assertEquals( 1, $quantity );
		}
		WC()->cart->empty_cart();
	}

	/**
	 * Test KCO_Request_Cart::get_item_unit_price
	 *
	 * @return void
	 */
	public function test_get_item_unit_price() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_25_inc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_12_inc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_6_inc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_0_inc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_25_exc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_12_exc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_6_exc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_price_0_exc = ( new KCO_Request_Cart() )->get_item_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 10000, $item_price_25_inc, 'get_order_tax_amount 25% inc tax' );
		$this->assertEquals( 10000, $item_price_12_inc, 'get_order_tax_amount 12% inc tax' );
		$this->assertEquals( 10000, $item_price_6_inc, 'get_order_tax_amount 6% inc tax' );
		$this->assertEquals( 10000, $item_price_0_inc, 'get_order_tax_amount 0% inc tax' );
		$this->assertEquals( 12500, $item_price_25_exc, 'get_order_tax_amount 25% exc tax' );
		$this->assertEquals( 11200, $item_price_12_exc, 'get_order_tax_amount 12% exc tax' );
		$this->assertEquals( 10600, $item_price_6_exc, 'get_order_tax_amount 6% exc tax' );
		$this->assertEquals( 10000, $item_price_0_exc, 'get_order_tax_amount 0% exc tax' );
	}

	/**
	 * Test KCO_Request_Cart::get_item_tax_rate
	 *
	 * @return void
	 */
	public function test_get_item_tax_rate() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product              = $this->get_product( $cart_item );
			$item_tax_rate_25_inc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product              = $this->get_product( $cart_item );
			$item_tax_rate_12_inc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product             = $this->get_product( $cart_item );
			$item_tax_rate_6_inc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product             = $this->get_product( $cart_item );
			$item_tax_rate_0_inc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product              = $this->get_product( $cart_item );
			$item_tax_rate_25_exc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product              = $this->get_product( $cart_item );
			$item_tax_rate_12_exc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product             = $this->get_product( $cart_item );
			$item_tax_rate_6_exc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product             = $this->get_product( $cart_item );
			$item_tax_rate_0_exc = ( new KCO_Request_Cart() )->get_item_tax_rate( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 2500, $item_tax_rate_25_inc, 'get_item_tax_rate 25% inc tax' );
		$this->assertEquals( 1200, $item_tax_rate_12_inc, 'get_item_tax_rate 12% inc tax' );
		$this->assertEquals( 600, $item_tax_rate_6_inc, 'get_item_tax_rate 6% inc tax' );
		$this->assertEquals( 0, $item_tax_rate_0_inc, 'get_item_tax_rate 0% inc tax' );
		$this->assertEquals( 2500, $item_tax_rate_25_exc, 'get_item_tax_rate 25% exc tax' );
		$this->assertEquals( 1200, $item_tax_rate_12_exc, 'get_item_tax_rate 12% exc tax' );
		$this->assertEquals( 600, $item_tax_rate_6_exc, 'get_item_tax_rate 6% exc tax' );
		$this->assertEquals( 0, $item_tax_rate_0_exc, 'get_item_tax_rate 0% exc tax' );

	}

	/**
	 * Test KCO_Request_Cart::get_item_total_amount
	 *
	 * @return void
	 */
	public function test_get_item_total_amount() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                  = $this->get_product( $cart_item );
			$item_total_amount_25_inc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                  = $this->get_product( $cart_item );
			$item_total_amount_12_inc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                 = $this->get_product( $cart_item );
			$item_total_amount_6_inc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                 = $this->get_product( $cart_item );
			$item_total_amount_0_inc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                  = $this->get_product( $cart_item );
			$item_total_amount_25_exc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                  = $this->get_product( $cart_item );
			$item_total_amount_12_exc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                 = $this->get_product( $cart_item );
			$item_total_amount_6_exc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                 = $this->get_product( $cart_item );
			$item_total_amount_0_exc = ( new KCO_Request_Cart() )->get_item_total_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 10000, $item_total_amount_25_inc, 'get_item_total_amount 25% inc tax' );
		$this->assertEquals( 10000, $item_total_amount_12_inc, 'get_item_total_amount 12% inc tax' );
		$this->assertEquals( 10000, $item_total_amount_6_inc, 'get_item_total_amount 6% inc tax' );
		$this->assertEquals( 10000, $item_total_amount_0_inc, 'get_item_total_amount 0% inc tax' );
		$this->assertEquals( 12500, $item_total_amount_25_exc, 'get_item_total_amount 25% exc tax' );
		$this->assertEquals( 11200, $item_total_amount_12_exc, 'get_item_total_amount 12% exc tax' );
		$this->assertEquals( 10600, $item_total_amount_6_exc, 'get_item_total_amount 6% exc tax' );
		$this->assertEquals( 10000, $item_total_amount_0_exc, 'get_item_total_amount 0% exc tax' );

	}

	/**
	 * Test KCO_Request_Cart::get_item_tax_amount
	 *
	 * @return void
	 */
	public function test_get_item_tax_amount() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                = $this->get_product( $cart_item );
			$item_tax_amount_25_inc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                = $this->get_product( $cart_item );
			$item_tax_amount_12_inc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product               = $this->get_product( $cart_item );
			$item_tax_amount_6_inc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product               = $this->get_product( $cart_item );
			$item_tax_amount_0_inc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->settup_cart( '25' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                = $this->get_product( $cart_item );
			$item_tax_amount_25_exc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product                = $this->get_product( $cart_item );
			$item_tax_amount_12_exc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product               = $this->get_product( $cart_item );
			$item_tax_amount_6_exc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0' );
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product               = $this->get_product( $cart_item );
			$item_tax_amount_0_exc = ( new KCO_Request_Cart() )->get_item_tax_amount( $cart_item, $product );
		}
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 2000, $item_tax_amount_25_inc, 'get_item_tax_amount 25% inc tax' );
		$this->assertEquals( 1071, $item_tax_amount_12_inc, 'get_item_tax_amount 12% inc tax' );
		$this->assertEquals( 566, $item_tax_amount_6_inc, 'get_item_tax_amount 6% inc tax' );
		$this->assertEquals( 0, $item_tax_amount_0_inc, 'get_item_tax_amount 0% inc tax' );
		$this->assertEquals( 2500, $item_tax_amount_25_exc, 'get_item_tax_amount 25% exc tax' );
		$this->assertEquals( 1200, $item_tax_amount_12_exc, 'get_item_tax_amount 12% exc tax' );
		$this->assertEquals( 600, $item_tax_amount_6_exc, 'get_item_tax_amount 6% exc tax' );
		$this->assertEquals( 0, $item_tax_amount_0_exc, 'get_item_tax_amount 0% exc tax' );
	}

	/**
	 * Test KCO_Request_Cart::get_shipping_name
	 *
	 * @return void
	 */
	public function test_get_shipping_name() {
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		$this->create_shipping_method();
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->cart->calculate_totals();
		$shipping_name = ( new KCO_Request_Cart() )->get_shipping_name();

		// Clear data.
		WC()->session->set( 'chosen_shipping_methods', array( '' ) );
		$this->delete_shipping_method();

		// Assertion.
		$this->assertEquals( 'Flat rate', $shipping_name, 'get_shipping_name' );
	}

	/**
	 * Test KCO_Request_Cart::get_shipping_amount
	 *
	 * @return void
	 */
	public function test_get_shipping_amount() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		// Create shipping method.
		$this->create_shipping_method();

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25', 'flat_rate' );
		$shipping_amount_25_inc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12', 'flat_rate' );
		$shipping_amount_12_inc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6', 'flat_rate' );
		$shipping_amount_6_inc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0', 'flat_rate' );
		$shipping_amount_0_inc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->settup_cart( '25', 'flat_rate' );
		$shipping_amount_25_exc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12', 'flat_rate' );
		$shipping_amount_12_exc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6', 'flat_rate' );
		$shipping_amount_6_exc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0', 'flat_rate' );
		$shipping_amount_0_exc = ( new KCO_Request_Cart() )->get_shipping_amount();
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 1250, $shipping_amount_25_inc, 'get_shipping_amount 25% inc tax' );
		$this->assertEquals( 1120, $shipping_amount_12_inc, 'get_shipping_amount 12% inc tax' );
		$this->assertEquals( 1060, $shipping_amount_6_inc, 'get_shipping_amount 6% inc tax' );
		$this->assertEquals( 1000, $shipping_amount_0_inc, 'get_shipping_amount 0% inc tax' );
		$this->assertEquals( 1250, $shipping_amount_25_exc, 'get_shipping_amount 25% exc tax' );
		$this->assertEquals( 1120, $shipping_amount_12_exc, 'get_shipping_amount 12% exc tax' );
		$this->assertEquals( 1060, $shipping_amount_6_exc, 'get_shipping_amount 6% exc tax' );
		$this->assertEquals( 1000, $shipping_amount_0_exc, 'get_shipping_amount 0% exc tax' );
	}

	/**
	 * Test KCO_Request_Cart::get_shipping_tax_amount
	 *
	 * @return void
	 */
	public function test_get_shipping_tax_amount() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		// Create shipping method.
		$this->create_shipping_method();

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25', 'flat_rate' );
		$shipping_tax_amount_25_inc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12', 'flat_rate' );
		$shipping_tax_amount_12_inc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6', 'flat_rate' );
		$shipping_tax_amount_6_inc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0', 'flat_rate' );
		$shipping_tax_amount_0_inc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->settup_cart( '25', 'flat_rate' );
		$shipping_tax_amount_25_exc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12', 'flat_rate' );
		$shipping_tax_amount_12_exc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6', 'flat_rate' );
		$shipping_tax_amount_6_exc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0', 'flat_rate' );
		$shipping_tax_amount_0_exc = ( new KCO_Request_Cart() )->get_shipping_tax_amount();
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 250, $shipping_tax_amount_25_inc, 'get_shipping_tax_amount 25% inc tax' );
		$this->assertEquals( 120, $shipping_tax_amount_12_inc, 'get_shipping_tax_amount 12% inc tax' );
		$this->assertEquals( 60, $shipping_tax_amount_6_inc, 'get_shipping_tax_amount 6% inc tax' );
		$this->assertEquals( 0, $shipping_tax_amount_0_inc, 'get_shipping_tax_amount 0% inc tax' );
		$this->assertEquals( 250, $shipping_tax_amount_25_exc, 'get_shipping_tax_amount 25% exc tax' );
		$this->assertEquals( 120, $shipping_tax_amount_12_exc, 'get_shipping_tax_amount 12% exc tax' );
		$this->assertEquals( 60, $shipping_tax_amount_6_exc, 'get_shipping_tax_amount 6% exc tax' );
		$this->assertEquals( 0, $shipping_tax_amount_0_exc, 'get_shipping_tax_amount 0% exc tax' );
	}

	/**
	 * Test KCO_Request_Cart::get_shipping_tax_rate
	 *
	 * @return void
	 */
	public function test_get_shipping_tax_rate() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		// Create shipping method.
		$this->create_shipping_method();

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->settup_cart( '25', 'flat_rate' );
		$shipping_tax_rate_25_inc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->settup_cart( '12', 'flat_rate' );
		$shipping_tax_rate_12_inc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->settup_cart( '6', 'flat_rate' );
		$shipping_tax_rate_6_inc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->settup_cart( '0', 'flat_rate' );
		$shipping_tax_rate_0_inc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->settup_cart( '25', 'flat_rate' );
		$shipping_tax_rate_25_exc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->settup_cart( '12', 'flat_rate' );
		$shipping_tax_rate_12_exc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->settup_cart( '6', 'flat_rate' );
		$shipping_tax_rate_6_exc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->settup_cart( '0', 'flat_rate' );
		$shipping_tax_rate_0_exc = ( new KCO_Request_Cart() )->get_shipping_tax_rate();
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 2500, $shipping_tax_rate_25_inc, 'get_shipping_tax_rate 25% inc tax' );
		$this->assertEquals( 1200, $shipping_tax_rate_12_inc, 'get_shipping_tax_rate 12% inc tax' );
		$this->assertEquals( 600, $shipping_tax_rate_6_inc, 'get_shipping_tax_rate 6% inc tax' );
		$this->assertEquals( 0, $shipping_tax_rate_0_inc, 'get_shipping_tax_rate 0% inc tax' );
		$this->assertEquals( 2500, $shipping_tax_rate_25_exc, 'get_shipping_tax_rate 25% exc tax' );
		$this->assertEquals( 1200, $shipping_tax_rate_12_exc, 'get_shipping_tax_rate 12% exc tax' );
		$this->assertEquals( 600, $shipping_tax_rate_6_exc, 'get_shipping_tax_rate 6% exc tax' );
		$this->assertEquals( 0, $shipping_tax_rate_0_exc, 'get_shipping_tax_rate 0% exc tax' );
	}

	/**
	 * Creates data for tests.
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

		$this->simple_product = ( new Krokedil_Simple_Product() )->create();

		// Default settings.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_kco_settings', $settings );
		update_option( 'woocommerce_currency', 'SEK' );
		update_option( 'woocommerce_allowed_countries', 'specific' );
		update_option( 'woocommerce_specific_allowed_countries', array( 'SE' ) );

		// Create cart.
		add_filter( 'woocommerce_customer_get_shipping_country', array( $this, 'set_shipping_country' ) );
		add_filter( 'woocommerce_customer_get_shipping_postcode', array( $this, 'set_shipping_postcode' ) );

		WC()->customer->set_billing_country( 'SE' );
	}

	/**
	 * Updates data for tests.
	 *
	 * @return void
	 */
	public function update() {
		return;
	}

	/**
	 * Gets data for tests.
	 *
	 * @return void
	 */
	public function view() {
		return;
	}

	/**
	 * Resets needed data for tests.
	 *
	 * @return void
	 */
	public function delete() {
		$this->simple_product->delete();
		$this->simple_product = null;
	}

	/**
	 * Helper to create tax rates and class.
	 *
	 * @param string $rate The tax rate.
	 * @return int
	 */
	public function create_tax_rate( $rate ) {
		// Create the tax class.
		WC_Tax::create_tax_class( "${rate}percent", "${rate}percent" );

		// Set tax data.
		$tax_data = array(
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => $rate,
			'tax_rate_name'     => "Vat $rate",
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => "${rate}percent",
		);
		return WC_Tax::_insert_tax_rate( $tax_data );
	}

	/**
	 * Force set shipping country.
	 *
	 * @return string
	 */
	public function set_shipping_country() {
		return 'SE';
	}

	/**
	 * Force set shipping postcode.
	 *
	 * @return string
	 */
	public function set_shipping_postcode() {
		return '12345';
	}

	/**
	 * Create shipping method helper function.
	 *
	 * @return void
	 */
	public function create_shipping_method() {
		Krokedil_WC_Shipping::create_simple_flat_rate();
	}

	/**
	 * Delete shipping method helper function.
	 *
	 * @return void
	 */
	public function delete_shipping_method() {
		Krokedil_WC_Shipping::delete_simple_flat_rate();
	}

	/**
	 * Helper function for test_get_order_tax_amount
	 *
	 * @return int
	 */
	public function get_order_tax_amount() {
		// Process cart data.
		$helper = new KCO_Request_Cart();
		$helper->process_data();

		// Get data.
		$order_lines      = $helper->get_order_lines();
		$order_tax_amount = $helper->get_order_tax_amount( $order_lines );

		return $order_tax_amount;
	}

	/**
	 * Helper function to get product from cart item.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return WC_Product
	 */
	public function get_product( $cart_item ) {
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}

		return $product;
	}

	/**
	 * Sets up the cart for the test.
	 *
	 * @param string      $tax_rate The tax rate to be used.
	 * @param string|bool $shipping The shipping to be used.
	 * @return void
	 */
	public function settup_cart( $tax_rate, $shipping = false ) {
		$this->simple_product->set_tax_class( $tax_rate . 'percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		if ( false !== $shipping ) {
			WC()->session->set( 'chosen_shipping_methods', array( $shipping ) );
		}
		WC()->cart->calculate_totals();
	}
}
