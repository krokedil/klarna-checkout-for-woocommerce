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
class KCO_Request_Create_Recurring extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $recurring_token The Klarna recurring token.
	 * @return array
	 */
	public function request( $order_id = null, $recurring_token = null ) {
		$request_url       = $this->get_api_url_base() . 'customer-token/v1/tokens/' . $recurring_token . '/order';
		$request_args      = apply_filters( 'kco_wc_create_recurring_order', $this->get_request_args( $order_id ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		$klarna_order_id = is_wp_error( $formated_response ) ? null : $formated_response['order_id'];

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'POST', 'KCO create recurring order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
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
		$order_data = new KCO_Request_Order();
		$order      = wc_get_order( $order_id );

		$request_body = array(
			'purchase_currency'   => $order->get_currency(),
			'order_amount'        => $order_data->get_order_amount( $order_id ),
			'order_lines'         => $order_data->get_order_lines( $order_id ),
			'order_tax_amount'    => $order_data->get_total_tax(),
			'merchant_reference1' => $order->get_order_number(),
			'merchant_reference2' => $order->get_id(),
		);

		return $request_body;
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
			'method'     => 'POST',
			'body'       => wp_json_encode( $this->get_body( $order_id ) ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}
}
