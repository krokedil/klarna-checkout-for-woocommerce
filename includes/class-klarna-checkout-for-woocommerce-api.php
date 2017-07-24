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
	 * Displays Klarna Checkout iframe.
	 *
	 * Checks if there's an ongoing Klarna order, to update it, or creates a new one.
	 */
	public static function show_iframe() {
		/**
		 * Logic
		 *
		 * Check if there's an ongoing Klarna order ID in WC session
		 * - YES
		 *   Try to get order see if it's OK
		 *   * YES
		 *     Display the iframe
		 *   * NO
		 *     Create new Klarna order, display the iframe
		 *
		 * - NO
		 *   Create new Klarna order, display the iframe
		 *
		 * @TODO: Continue here
		 */

		$klarna_order = self::get_klarna_order();

		$response = self::create_checkout();
		$decoded_response = json_decode( $response['body'] );
		$klarna_order_id = $decoded_response->order_id;

		$klarna_order = wp_safe_remote_get(
			'https://api-na.playground.klarna.com/checkout/v3/orders/' . $klarna_order_id,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'N100033:riz5Aith3bii%ch9' ),
					'Content-Type'  => 'application/json',
				),
			)
		);

		$decoded_klarna_order = json_decode( $klarna_order['body'] );
		echo $decoded_klarna_order->html_snippet;
	}

	/**
	 * Gets an ongoing or creates a new Klarna order.
	 */
	public static function get_klarna_order() {
		if ( WC()->session->get( 'klarna_checkout_order_id' ) ) {
			$klarna_order_id = WC()->session->get( 'klarna_checkout_order_id' );
			$klarna_order = self::get_klarna_order( $klarna_order_id );
		} else {
			$klarna_order = self::create_klarna_order();
		}

		return $klarna_order;
	}

	/**
	 * Create Klarna Checkout resource.
	 */
	public static function create_checkout() {
		$order_lines = self::get_order_lines();
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
				'order_amount'      => $order_lines['order_amount'],
				'order_tax_amount'  => $order_lines['order_tax_amount'],
				'order_lines'       => $order_lines['order_lines'],
				'merchant_urls'     => array(
					'terms'                  => 'http://krokedil.klarna.ngrok.io/terms.html',
					'checkout'               => 'http://krokedil.klarna.ngrok.io/checkout/',
					'confirmation'           => 'http://krokedil.klarna.ngrok.io/checkout/kco-confirm/',
					'push'                   => 'http://krokedil.klarna.ngrok.io/api/push',
					// 'validation'             => 'https://krokedil.klarna.ngrok.io/api/validation',
					// 'shipping_option_update' => 'https://krokedil.klarna.ngrok.io/api/shipment',
					// 'address_update'         => 'https://krokedil.klarna.ngrok.io/api/address',
					// 'notification'           => 'https://krokedil.klarna.ngrok.io/api/pending',
					// 'country_change'         => 'https://krokedil.klarna.ngrok.io/api/country',
				),
			) ),
		);

		$response = wp_safe_remote_post( $request_url, $request_args );
		return $response;
	}

	/**
	 * Gets WooCommerce order lines formatted for Klarna Rest API.
	 *
	 * @return array
	 */
	public static function get_order_lines() {
		$order_lines_processor = new Klarna_Checkout_For_WooCommerce_Order_Lines();
		$order_lines = $order_lines_processor->order_lines();

		return $order_lines;
	}

}