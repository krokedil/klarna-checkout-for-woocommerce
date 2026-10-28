<?php
/**
 * Create KCO Order
 *
 * @package Klarna_Checkout/Classes/Request/Checkout/Get
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create KCO Order
 */
class KCO_Request_Retrieve extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $klarna_order_id The Kustom order id.
	 * @return array
	 */
	public function request( $klarna_order_id ) {
		$request_url = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;

		/**
		 * Filters the request arguments for retrieving an order from Kustom.
		 *
		 * @param array $request_args The request arguments passed to wp_remote_request().
		 */
		$request_args      = apply_filters( 'kco_wc_get_order', $this->get_request_args( $request_url ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'GET', 'KCO get order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $url The request URL.
	 * @return array
	 */
	protected function get_request_args( $url = '' ) {
		/**
		 * Filters the timeout, in seconds, for requests to the Kustom API.
		 *
		 * @param int $timeout The request timeout in seconds. Default 10.
		 */
		$timeout = apply_filters( 'kco_wc_request_timeout', 10 );

		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent( $url ),
			'method'     => 'GET',
			'timeout'    => $timeout,
		);
	}
}
