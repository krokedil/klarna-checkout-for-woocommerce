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
class Test_KCO_Api extends AKrokedil_Unit_Test_Case {
	public function test_default_woo_settings() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21452.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '5',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 1999.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 11994.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '6',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1277.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 5108.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '7',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 666.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 666.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '8',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1842.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 3684.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 0.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_zero_decimals() {
		WC()->cart->calculate_totals();

		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21500.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '9',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 12000.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '10',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1300.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 5100.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '11',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 700.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 700.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '12',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1800.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 3600.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 0.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_25_tax_inc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21300.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '13',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 12000.0,
					'total_tax_amount'      => 2400.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '14',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1300.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 5100.0,
					'total_tax_amount'      => 1020.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '15',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 700.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 700.0,
					'total_tax_amount'      => 140.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '16',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1800.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 3600.0,
					'total_tax_amount'      => 720.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 4280.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_12_tax_inc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21500.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '17',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 12000.0,
					'total_tax_amount'      => 1286.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '18',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1300.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 5100.0,
					'total_tax_amount'      => 546.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '19',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 700.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 700.0,
					'total_tax_amount'      => 75.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '20',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1800.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 3600.0,
					'total_tax_amount'      => 386.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 2293.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_6_tax_inc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21400.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '21',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 600.0,
					'total_amount'          => 12000.0,
					'total_tax_amount'      => 679.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '22',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1300.0,
					'tax_rate'              => 600.0,
					'total_amount'          => 5100.0,
					'total_tax_amount'      => 289.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '23',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 700.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 600.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '24',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1800.0,
					'tax_rate'              => 600.0,
					'total_amount'          => 3600.0,
					'total_tax_amount'      => 204.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 1172.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_0_tax_inc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21500.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '25',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 12000.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '26',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1300.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 5100.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '27',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 700.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 700.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '28',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1800.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 3600.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 0.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_25_tax_exc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 26900.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '29',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2500.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 15000.0,
					'total_tax_amount'      => 3000.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '30',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1600.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 6400.0,
					'total_tax_amount'      => 1280.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '31',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 900.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 800.0,
					'total_tax_amount'      => 160.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '32',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 2300.0,
					'tax_rate'              => 2500.0,
					'total_amount'          => 4600.0,
					'total_tax_amount'      => 920.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 5360.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_12_tax_exc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 24000.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '33',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2200.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 13200.0,
					'total_tax_amount'      => 1414.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '34',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1500.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 5700.0,
					'total_tax_amount'      => 611.0,
					'total_discount_amount' => 300.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '35',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 800.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 700.0,
					'total_tax_amount'      => 75.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '36',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 1200.0,
					'total_amount'          => 4000.0,
					'total_tax_amount'      => 429.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 2529.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_6_tax_exc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21500.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '37',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 12000.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '38',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1300.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 5100.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '39',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 700.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 700.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '40',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1800.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 3600.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 0.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
	}

	public function test_0_tax_exc() {
		$expected = array(
			'purchase_country'   => 'SE',
			'locale'             => 'en-US',
			'merchant_urls'      =>
			array(
				'terms'                  => false,
				'checkout'               => 'http://example.org',
				'confirmation'           => 'http://example.org?kco_confirm=yes&kco_order_id={checkout.order.id}',
				'push'                   => 'http://example.org/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=',
				'shipping_option_update' => 'https://example.org/wc-api/KCO_WC_Shipping_Option_Update/',
				'notification'           => 'http://example.org/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}',
			),
			'billing_countries'  =>
			array(
				0 => 'SE',
			),
			'shipping_countries' =>
			array(
				0 => 'SE',
			),
			'merchant_data'      => '{"is_user_logged_in":false}',
			'options'            =>
			array(
				'title_mandatory'                          => true,
				'allow_separate_shipping_address'          => false,
				'date_of_birth_mandatory'                  => false,
				'national_identification_number_mandatory' => false,
				'allowed_customer_types'                   =>
				array(
					0 => 'person',
				),
				'require_client_validation'                => true,
				'require_client_validation_callback_response' => true,
				'phone_mandatory'                          => true,
			),
			'customer'           =>
			array(
				'type' => 'person',
			),
			'purchase_currency'  => 'SEK',
			'order_amount'       => 21500.0,
			'order_lines'        =>
			array(
				0 =>
				array(
					'reference'             => '41',
					'name'                  => 'Default product name',
					'quantity'              => 6.0,
					'unit_price'            => 2000.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 12000.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name',
				),
				1 =>
				array(
					'reference'             => '42',
					'name'                  => 'Default product name',
					'quantity'              => 4.0,
					'unit_price'            => 1300.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 5100.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 100.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-2',
				),
				2 =>
				array(
					'reference'             => '43',
					'name'                  => 'Default product name',
					'quantity'              => 1.0,
					'unit_price'            => 700.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 700.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-3',
				),
				3 =>
				array(
					'reference'             => '44',
					'name'                  => 'Default product name',
					'quantity'              => 2.0,
					'unit_price'            => 1800.0,
					'tax_rate'              => 0.0,
					'total_amount'          => 3600.0,
					'total_tax_amount'      => 0.0,
					'total_discount_amount' => 0.0,
					'type'                  => 'physical',
					'product_url'           => 'http://example.org/?product=default-product-name-4',
				),
			),
			'order_tax_amount'   => 0.0,
			'billing_address'    =>
			array(
				'email'           => null,
				'postal_code'     => null,
				'country'         => 'SE',
				'phone'           => null,
				'given_name'      => null,
				'family_name'     => null,
				'street_address'  => null,
				'street_address2' => null,
				'city'            => null,
				'region'          => null,
			),
		);
		// Settup simple cart.
		$create_request_data = new KCO_Request_Create();
		$result              = $create_request_data->get_body( null );
		$this->assertEquals( $expected, $result );
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
		// Pick settings based on test.
		switch ( $this->getName() ) {
			case 'test_zero_decimals':
				update_option( 'woocommerce_price_num_decimals', 0 );
				break;
			case 'test_25_tax_inc':
				update_option( 'woocommerce_calc_taxes', 'yes' );
				update_option( 'woocommerce_prices_include_tax', 'yes' );
				$this->tax_rate_id = $this->create_tax_rate( '25' );
				break;
			case 'test_12_tax_inc':
				update_option( 'woocommerce_calc_taxes', 'yes' );
				update_option( 'woocommerce_prices_include_tax', 'yes' );
				$this->tax_rate_id = $this->create_tax_rate( '12' );
				break;
			case 'test_6_tax_inc':
				update_option( 'woocommerce_prices_include_tax', 'yes' );
				update_option( 'woocommerce_calc_taxes', 'yes' );
				$this->tax_rate_id = $this->create_tax_rate( '6' );
				break;
			case 'test_0_tax_inc':
				update_option( 'woocommerce_calc_taxes', 'yes' );
				update_option( 'woocommerce_prices_include_tax', 'yes' );
				$this->tax_rate_id = $this->create_tax_rate( '0' );
				break;
			case 'test_25_tax_exc':
				update_option( 'woocommerce_calc_taxes', 'yes' );
				update_option( 'woocommerce_prices_include_tax', 'no' );
				$this->tax_rate_id = $this->create_tax_rate( '25' );
				break;
			case 'test_12_tax_exc':
				update_option( 'woocommerce_calc_taxes', 'yes' );
				update_option( 'woocommerce_prices_include_tax', 'no' );
				$this->tax_rate_id = $this->create_tax_rate( '12' );
				break;
			case 'test_6_tax_exc':
				update_option( 'woocommerce_prices_include_tax', 'yes' );
				update_option( 'woocommerce_calc_taxes', 'no' );
				$this->tax_rate_id = $this->create_tax_rate( '6' );
				break;
			case 'test_0_tax_exc':
				update_option( 'woocommerce_calc_taxes', 'yes' );
				update_option( 'woocommerce_prices_include_tax', 'no' );
				$this->tax_rate_id = $this->create_tax_rate( '0' );
				break;
		}

		// Default settings.
		update_option( 'woocommerce_kco_settings', $settings );
		update_option( 'woocommerce_currency', 'SEK' );
		update_option( 'woocommerce_allowed_countries', 'specific' );
		update_option( 'woocommerce_specific_allowed_countries', array( 'SE' ) );

		// Create products.
		$simple_products    = array();
		$simple_products[6] = ( new Krokedil_Simple_Product( array( 'regular_price' => 19.99 ) ) )->create();
		$simple_products[4] = ( new Krokedil_Simple_Product( array( 'regular_price' => 12.77 ) ) )->create();
		$simple_products[1] = ( new Krokedil_Simple_Product( array( 'regular_price' => 6.66 ) ) )->create();
		$simple_products[2] = ( new Krokedil_Simple_Product( array( 'regular_price' => 18.42 ) ) )->create();

		$this->simple_products = $simple_products;

		// Create cart.
		WC()->customer->set_billing_country( 'SE' );
		foreach ( $simple_products as $quantity => $simple_product ) {
			WC()->cart->add_to_cart( $simple_product->get_id(), $quantity );
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
			$simple_product->delete();
		}
		$this->simple_products = null;
		if ( $this->tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $this->tax_rate_id );
			$this->tax_rate_id = null;
		}
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
