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
	 * API base address
	 *
	 * @var $api_base
	 */
	private static $api_base = 'https://api-na.playground.klarna.com';

	/**
	 * Create checkout endpoint
	 *
	 * @var $create_checkout_endpoint
	 */
	private static $create_checkout_endpoint = '/checkout/v3/order';

	/**
	 * Fetch checkout endpoint
	 *
	 * @var $update_checkout_endpoint
	 */
	private static $fetch_checkout_endpoint = '/checkout/v3/orders/';

	/**
	 * Update checkout endpoint
	 *
	 * @var $update_checkout_endpoint
	 */
	private static $update_checkout_endpoint = '/checkout/v3/orders/';

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

		$klarna_order = self::order();
		echo $klarna_order->html_snippet;




		/*
		$response = self::create_order();
		$decoded_response = json_decode( $response['body'] );
		$klarna_order_id = $decoded_response->order_id;

		$klarna_order = wp_safe_remote_get(
			API_BASE . FETCH_CHECKOUT_ENDPOINT . $klarna_order_id,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'N100033:riz5Aith3bii%ch9' ),
					'Content-Type'  => 'application/json',
				),
			)
		);

		$decoded_klarna_order = json_decode( $klarna_order['body'] );
		echo $decoded_klarna_order->html_snippet;
		*/
	}

	/**
	 * Gets Klarna order.
	 *
	 * Gets an ongoing or creates a new Klarna order and returns it.
	 */
	public static function order() {
		WC()->session->__unset( 'klarna_checkout_order_id' );

		if ( WC()->session->get( 'klarna_checkout_order_id' ) ) {
			$klarna_order_id = WC()->session->get( 'klarna_checkout_order_id' );
			$klarna_order = self::retrieve_order( $klarna_order_id );

			if ( is_wp_error( $klarna_order ) ) {
				$klarna_order = self::create_order();

			}
		} else {
			$klarna_order = self::create_order();
		}

		return $klarna_order;
	}

	/**
	 * Create Klarna Checkout resource.
	 */
	public static function create_order() {
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

		if ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) {
			$klarna_order = json_decode( $response['body'] );
			WC()->session->set( 'klarna_checkout_order_id', $klarna_order->order_id );

			return $klarna_order;
		} else {
			return new WP_Error( 'Error creating Klarna order.' );
		}
	}

	/**
	 * Retrieve ongoing Klarna order.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 * @return object $klarna_order    Klarna order.
	 */
	public static function retrieve_order( $klarna_order_id ) {
		$response = wp_safe_remote_get(
			'https://api-na.playground.klarna.com/checkout/v3/orders/' . $klarna_order_id,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'N100033:riz5Aith3bii%ch9' ),
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) {
			$klarna_order = json_decode( $response['body'] );
			return $klarna_order;
		} else {
			return new WP_Error( 'Error retrieving Klarna order.' );
		}
	}

	/**
	 * Update ongoing Klarna order.
	 */
	public static function update_order() {

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