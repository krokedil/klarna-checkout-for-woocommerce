<?php
/**
 * Set KCO Order Merchant Reference
 *
 * @package Klarna_Checkout/Classes/Request/Order-Management/Patch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update KCO Order
 */
class KCO_Request_Set_Merchant_Reference extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $klarna_order_id The Klarna order id.
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	public function request( $klarna_order_id, $order_id ) {
		$request_url       = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id . '/merchant-references';
		$request_args      = apply_filters( 'kco_wc_acknowledge_order', $this->get_request_args( $order_id ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'PATCH', 'KCO set merchant reference', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_body( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'merchant_reference1' => $order->get_order_number(),
			'merchant_reference2' => $order_id,
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	protected function get_request_args( $order_id ) {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'PATCH',
			'body'       => wp_json_encode( $this->get_body( $order_id ) ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}
}
