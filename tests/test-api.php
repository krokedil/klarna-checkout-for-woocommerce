<?php
/**
 * Class SampleTest
 *
 * @package Klarna_Checkout_For_Woocommerce
 */

/**
 * Sample test case.
 */
class KCO_WC_API_Tests extends WP_UnitTestCase {

	/**
	 * Test creating Klarna order resource.
	 */
	function test_create_order() {
		$request_url = 'https://api-na.playground.klarna.com/checkout/v3/orders';
		$request_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'N100033:riz5Aith3bii%ch9' ),
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'purchase_country'  => 'US',
				'purchase_currency' => 'USD',
				'locale'            => 'en-US',
				'order_amount'      => 50000,
				'order_tax_amount'  => 0,
				'order_lines'       => array(
					array(
						'type' => 'physical',
						'reference' => '19-402-USA',
						'name' => 'Red T-Shirt',
						'quantity' => 5,
						'unit_price' => 10000,
						'tax_rate' => 0,
						'total_amount' => 50000,
						'total_discount_amount' => 0,
						'total_tax_amount' => 0,
					),
				),
				'merchant_urls'     => array(
					'terms'                  => 'http://krokedil.klarna.ngrok.io/terms.html',
					'checkout'               => 'http://krokedil.klarna.ngrok.io/checkout/',
					'confirmation'           => 'http://krokedil.klarna.ngrok.io/checkout/kco-confirm/',
					'push'                   => 'http://krokedil.klarna.ngrok.io/api/push',
				),
			) ),
		);

		$response = wp_safe_remote_post( $request_url, $request_args );
		$klarna_order = json_decode( $response['body'] );

		$this->assertTrue( 'string' === gettype( $klarna_order->order_id ) );
	}
}
