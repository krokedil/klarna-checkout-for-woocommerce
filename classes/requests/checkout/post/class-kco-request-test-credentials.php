<?php
/**
 * Create KCO Order
 *
 * @package Klarna_Checkout/Classes/Request/Checkout/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create KCO Order
 */
class KCO_Request_Test_Credentials extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $username The username to use.
	 * @param string $password The password to use.
	 * @param bool   $testmode If its test mode or not.
	 * @param string $endpoint The endpoint for the request.
	 * @return array
	 */
	public function request( $username, $password, $testmode, $endpoint ) {
		$request_url       = $this->get_test_endpoint( $testmode, $endpoint ) . 'checkout/v3/orders';
		$request_args      = apply_filters( 'kco_wc_test_credentials', $this->get_request_args( $username, $password ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = KCO_Logger::format_log( null, 'POST', 'KCO test credentials', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		$request_body = array(
			'purchase_country'  => $this->get_purchase_country(),
			'purchase_currency' => get_woocommerce_currency(),
			'locale'            => 'en-US',
			'order_amount'      => 100,
			'order_tax_amount'  => 0,
			'order_lines'       => array(
				array(
					'name'             => 'Test Credentials product',
					'quantity'         => 1,
					'unit_price'       => 100,
					'tax_rate'         => 0,
					'total_amount'     => 100,
					'total_tax_amount' => 0,
				),
			),
			'merchant_urls'     => KCO_WC()->merchant_urls->get_urls(),
		);

		return $request_body;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $username The username to use.
	 * @param string $password The password to use.
	 * @return array
	 */
	protected function get_request_args( $username, $password ) {
		return array(
			'headers'    => array(
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
				'Content-Type'  => 'application/json',
			),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'POST',
			'body'       => wp_json_encode( $this->get_body() ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}

	/**
	 * Gets the endpoint for the test.
	 *
	 * @param bool   $testmode If its test mode or not.
	 * @param string $endpoint The endpoint for the request.
	 */
	public function get_test_endpoint( $testmode, $endpoint ) {
		$country_string = 'US' === $endpoint ? '-na' : '';
		$test_string    = $testmode ? '.playground' : '';

		return 'https://api' . $country_string . $test_string . '.klarna.com/';
	}

	/**
	 * Checks response for any error.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return object|array
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		// Check if response is a WP_Error, and return it back if it is.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check the status code, if its not between 200 and 299 then its an error.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$error_message = wp_json_encode( $response['response'] );
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $error_message );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
