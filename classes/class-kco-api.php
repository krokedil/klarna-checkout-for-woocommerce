<?php
/**
 * API Class file.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_API class.
 *
 * Class that has functions for the klarna communication.
 */
class KCO_API {
	/**
	 * Creates a Klarna Checkout order.
	 *
	 * @return mixed
	 */
	public function create_klarna_order() {
		$request  = new KCO_Request_Create();
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Gets a Klarna Checkout order
	 *
	 * @param string $klarna_order_id The Klarna Checkout order id.
	 * @return mixed
	 */
	public function get_klarna_order( $klarna_order_id ) {
		$request  = new KCO_Request_Retrieve();
		$response = $request->request( $klarna_order_id );

		return $this->check_for_api_error( $response );
	}

	/**
	 * Updates a Klarna Checkout order.
	 *
	 * @param string $klarna_order_id The Klarna Checkout order id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return mixed
	 */
	public function update_klarna_order( $klarna_order_id, $order_id = null ) {
		$request  = new KCO_Request_Update();
		$response = $request->request( $klarna_order_id, $order_id );

		return $this->check_for_api_error( $response );
	}

	/**
	 * Sets the merchant reference for the Klarna order. Goes to the order management API.
	 *
	 * @param string $klarna_order_id The Klarna Checkout order id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return mixed
	 */
	public function set_merchant_reference( $klarna_order_id, $order_id ) {
		$request  = new KCO_Request_Set_Merchant_Reference();
		$response = $request->request( $klarna_order_id, $order_id );

		return $this->check_for_api_error( $response );
	}

	/**
	 * Acknowledges the order with Klarna. Goes to the order management API.
	 *
	 * @param string $klarna_order_id The Klarna Checkout order id.
	 * @return mixed
	 */
	public function acknowledge_klarna_order( $klarna_order_id ) {
		$request  = new KCO_Request_Acknowledge_Order();
		$response = $request->request( $klarna_order_id );

		return $this->check_for_api_error( $response );
	}

	/**
	 * Checks for WP Errors and returns either the response as array or a false.
	 *
	 * @param array $response The response from the request.
	 * @return mixed
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			kco_extract_error_message( $response );
			return false;
		}
		return $response;
	}
}
