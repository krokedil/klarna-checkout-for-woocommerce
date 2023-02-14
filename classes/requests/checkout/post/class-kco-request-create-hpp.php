<?php
/**
 * Create HPP session request class.
 *
 * @package Klarna_Checkout/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create HPP session request class.
 */
class KCO_Request_Create_HPP extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $session_id The Klarna Checkout session id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array
	 */
	public function request( $session_id, $order_id ) {
		$request_url  = $this->get_api_url_base() . 'hpp/v1/sessions';
		$request_args = apply_filters( 'wc_klarna_checkout_create_hpp_args', $this->get_request_args( $session_id, $order_id ) );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log request.
		$log = KCO_Logger::format_log( $session_id, 'POST', 'KCO create HPP', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $session_id The Klarna Payment session id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_request_args( $session_id, $order_id ) {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'POST',
			'body'       => wp_json_encode( apply_filters( 'kco_wc_api_hpp_request_args', $this->get_body( $session_id, $order_id ), $order_id, $session_id ) ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}

	/**
	 * Gets the request body for the API call.
	 *
	 * @param string $session_id The Klarna Checkout session id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return string
	 */
	public function get_body( $session_id, $order_id ) {
		$order = wc_get_order( $order_id );

		$success_url = add_query_arg(
			array(
				'sid'          => '{{session_id}}',
				'kco_confirm'  => 'yes',
				'kco_order_id' => $session_id,
			),
			$order->get_checkout_order_received_url()
		);

		return array(
			'payment_session_url' => $this->get_api_url_base() . 'checkout/v3/orders/' . $session_id,
			'merchant_urls'       => array(
				'success' => $success_url,
				'cancel'  => $order->get_checkout_payment_url(),
				'back'    => $order->get_checkout_payment_url(),
				'failure' => $order->get_checkout_payment_url(),
				'error'   => $order->get_checkout_payment_url(),
			),
		);
	}
}
