<?php
/**
 * Update KCO Confirmation.
 *
 * @package Klarna_Checkout/Classes/Request/Checkout/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update KCO Confirmation.
 */
class KCO_Request_Update_Confirmation extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $klarna_order_id The Klarna order id.
	 * @param array  $klarna_order The Klarna order to be modified.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array
	 */
	public function request( $klarna_order_id, $klarna_order, $order_id ) {
		$request_url       = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;
		$request_args      = apply_filters( 'kco_wc_update_order', $this->get_request_args( $klarna_order, $order_id ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'POST', 'KCO update confirmation', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param array  $klarna_order The Klarna order to be modified.
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_body( $klarna_order, $order_id ) {
		$order = wc_get_order( $order_id );
		// Use the old Klarna order from a get request to prevent changing more then we need.
		$request_args                                  = array(
			'purchase_country'    => $klarna_order['purchase_country'],
			'purchase_currency'   => $klarna_order['purchase_currency'],
			'locale'              => $klarna_order['locale'],
			'merchant_urls'       => $klarna_order['merchant_urls'],
			'order_amount'        => $klarna_order['order_amount'],
			'order_lines'         => $klarna_order['order_lines'],
			'order_tax_amount'    => $klarna_order['order_tax_amount'],
			'billing_countries'   => $klarna_order['billing_countries'],
			'shipping_countries'  => $klarna_order['shipping_countries'],
			'merchant_data'       => $klarna_order['merchant_data'],
			'options'             => $klarna_order['options'],
			'merchant_reference1' => $order->get_order_number(),
			'merchant_reference2' => $order_id,
		);
		$request_args['merchant_urls']['confirmation'] = add_query_arg(
			array(
				'kco_confirm'  => 'yes',
				'kco_order_id' => '{checkout.order.id}',
			),
			$order->get_checkout_order_received_url()
		);
		return $request_args;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param array  $klarna_order The Klarna order to be modified.
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	protected function get_request_args( $klarna_order, $order_id ) {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'POST',
			'body'       => wp_json_encode( apply_filters( 'kco_wc_api_request_args', $this->get_body( $klarna_order, $order_id ), $order_id ) ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}
}
