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
		$request_url       = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;
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
	 * Checks response for any error.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return array|false|WP_Error
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		if ( ! is_wp_error( $response ) && 404 === (int) wp_remote_retrieve_response_code( $response ) ) {
			if ( WC()->session ) {
				WC()->session->__unset( 'kco_wc_order_id' );
			}
			KCO_Logger::log( 'KCO retrieve: 404 response received. Checkout session has expired. Clearing stored order ID.' );
			return false;
		}
		return parent::process_response( $response, $request_args, $request_url );
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $url The request URL.
	 * @return array
	 */
	protected function get_request_args( $url = '' ) {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent( $url ),
			'method'     => 'GET',
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}
}
