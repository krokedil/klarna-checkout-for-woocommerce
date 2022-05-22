<?php
/**
 * Upsell a Klarna Order
 *
 * @package Klarna_Checkout/Classes/Request/Order-Management/Patch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update KCO Order
 */
class KCO_Request_Upsell_Order extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $klarna_order_id The Klarna order id.
	 * @param string $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The unique id for the upsell request.
	 * @return array
	 */
	public function request( $klarna_order_id, $order_id, $upsell_uuid ) {
		$request_url       = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id . '/authorization';
		$request_args      = apply_filters( 'kco_wc_acknowledge_order', $this->get_request_args( $order_id, $upsell_uuid ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'PATCH', 'KCO upsell order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The unique id for the upsell request.
	 * @return array
	 */
	public function get_body( $order_id, $upsell_uuid ) {
		$helper = new KCO_Request_Order();
		return array(
			'order_lines'  => $helper->get_order_lines( $order_id ),
			'order_amount' => $helper->get_order_amount( $order_id ),
			'description'  => __( 'Upsell from thankyou page', 'klarna-upsell-for-woocommerce' ),
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The unique id for the upsell request.
	 * @return array
	 */
	protected function get_request_args( $order_id, $upsell_uuid ) {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'PATCH',
			'body'       => wp_json_encode( $this->get_body( $order_id, $upsell_uuid ) ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}

	/**
	 * Checks response for any error. We might need this to handle special cases with headers.
	 *
	 * @TODO follow up with klarna on how a rejected message looks.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return object|array
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		// If its a WP Error, just let the parent deal with it.
		if ( is_wp_error( $response ) ) {
			return parent::process_response( $response, $request_args, $request_url );
		}

		return parent::process_response( $response, $request_args, $request_url );
	}
}
