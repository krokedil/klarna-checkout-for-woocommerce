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

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );
		// Check the status code, if its not between 200 and 299 then its an error.
		if ( $code < 200 || $code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $this->sanitize_request_args( $request_args ) );
			$error_message = '';
			// Get the error messages.
			$errors = json_decode( $body, true );
			if ( empty( $errors ) ) {

				if ( 404 === $code ) {
					return new WP_Error( $code, 'The order was not found in Kustom. Attempting to retrieve the order again.', $data );
				}

				return new WP_Error( $code, 'received empty body', $data );
			} elseif ( isset( $errors['error_messages'] ) && is_array( $errors['error_messages'] ) ) {
				foreach ( $errors['error_messages'] as $error ) {
					$error_message = "$error_message  $error";
				}
			}
			return new WP_Error( $code, "$body $error_message", $data );
		}
		return json_decode( $body, true );
	}
}
