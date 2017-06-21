<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_API class.
 *
 * Class that talks to KCO API, wrapper for V2 and V3.
 */
class Klarna_Checkout_For_WooCommerce_API {

	/**
	 * API URLs
	 * - https://api.klarna.com/
	 * - https://api-na.klarna.com/
	 * - https://api.playground.klarna.com/
	 * - https://api-na.playground.klarna.com/
	 *
	 * Authentication
	 *
	 * Checkout API
	 * - Create checkout (order)
	 * - Retrieve checkout
	 * - Update checkout
	 *
	 * Checkout API Callbacks
	 * - Address Update
	 * - Shipping Option Update
	 * - Order Validation
	 */

	/**
	 * Create checkout endpoint
	 */
	const CREATE_CHECKOUT_ENDPOINT = '/checkout/v3/order';

	/**
	 * Fetch checkout endpoint
	 */
	const FETCH_CHECKOUT_ENDPOINT = '/checkout/v3/orders/{order_id}';

	/**
	 * Update checkout endpoint
	 */
	const UPDATE_CHECKOUT_ENDPOINT = '/checkout/v3/orders/{order_id}';

	/**
	 * Klarna Checkout merchant ID.
	 *
	 * @var string
	 */
	private static $merchant_id = '';

	/**
	 * Klarna Checkout shared secret.
	 *
	 * @var string
	 */
	private static $shared_secret = '';

	/**
	 * Set Klarna Checkout merchant ID.
	 *
	 * @param string $merchant_id Klarna Checkout merchant ID.
	 */
	public static function set_merchant_id( $merchant_id ) {

	}

	/**
	 * Set Klarna Checkout shared secret.
	 *
	 * @param string $shared_secret Klarna Checkout shared secret.
	 */
	public static function set_shared_secret( $shared_secret ) {

	}



	/**
	 * Create Klarna Checkout resource.
	 */
	public static function create_checkout() {
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
				'order_amount'      => 10000,
				'order_tax_amount'  => 2000,
				'order_lines'       => array(
					array(
						'type'             => 'physical',
						'reference'        => '123050',
						'name'             => 'Tomatoes',
						'quantity'         => 10,
						'quantity_unit'    => 'kg',
						'unit_price'       => 600,
						'tax_rate'         => 2500,
						'total_amount'     => 6000,
						'total_tax_amount' => 1200,
					),
					array(
						'type'                  => 'physical',
						'reference'             => '543670',
						'name'                  => 'Bananas',
						'quantity'              => 1,
						'quantity_unit'         => 'bag',
						'unit_price'            => 5000,
						'tax_rate'              => 2500,
						'total_amount'          => 4000,
						'total_discount_amount' => 1000,
						'total_tax_amount'      => 800,
					),
				),
				'merchant_urls'     => array(
					'terms'                  => 'https://krokedil.klarna.ngrok.io/terms.html',
					'checkout'               => 'https://krokedil.klarna.ngrok.io/checkout.html',
					'confirmation'           => 'https://krokedil.klarna.ngrok.io/confirmation.html',
					'push'                   => 'https://krokedil.klarna.ngrok.io/api/push',
					'validation'             => 'https://krokedil.klarna.ngrok.io/api/validation',
					'shipping_option_update' => 'https://krokedil.klarna.ngrok.io/api/shipment',
					'address_update'         => 'https://krokedil.klarna.ngrok.io/api/address',
					'notification'           => 'https://krokedil.klarna.ngrok.io/api/pending',
					'country_change'         => 'https://krokedil.klarna.ngrok.io/api/country',
				),
			) ),
		);

		$response = wp_safe_remote_post( $request_url, $request_args );
		return $response;
	}

}