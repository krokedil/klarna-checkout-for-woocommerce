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
	 * @param bool   $force If true always update the order, even if not needed.
	 * @return mixed
	 */
	public function update_klarna_order( $klarna_order_id, $order_id = null, $force = false ) {
		$request  = new KCO_Request_Update();
		$response = $request->request( $klarna_order_id, $order_id, $force );

		// If the update results in a READ_ONLY_ORDER response, let's try to redirect the customer to thank you page.
		if ( is_wp_error( $response ) ) {

			// Data is returned as both json and string. Let's try to grab only the json data.
			$extracted_response = strstr( $response->get_error_message(), '}', true ) . '}';
			$extracted_response = json_decode( $extracted_response );
			if ( 'READ_ONLY_ORDER' === $extracted_response->error_code ) {

				$query_args = array(
					'fields'      => 'ids',
					'post_type'   => wc_get_order_types(),
					'post_status' => array_keys( wc_get_order_statuses() ),
					'meta_key'    => '_wc_klarna_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
					'meta_value'  => $klarna_order_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
					'date_query'  => array(
						array(
							'after' => '2 day ago',
						),
					),
				);

				$orders   = get_posts( $query_args );
				$order_id = $orders[0];
				$order    = wc_get_order( $order_id );

				if ( $order ) {
					wp_safe_redirect( $order->get_checkout_order_received_url() );
					exit;
				}
			}
		}

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
	 * Acknowledges the order with Klarna. Goes to the order management API.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $recurring_token The Klarna recurring token.
	 * @return mixed
	 */
	public function create_recurring_order( $order_id, $recurring_token ) {
		$request  = new KCO_Request_Create_Recurring();
		$response = $request->request( $order_id, $recurring_token );

		return $response;
	}

	/**
	 * Acknowledges the order with Klarna. Goes to the order management API.
	 *
	 * @param string $klarna_order_id The Klarna Checkout order id.
	 * @return mixed
	 */
	public function get_klarna_om_order( $klarna_order_id ) {
		$request  = new KCO_Request_Get_Order();
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
			kco_print_error_message( $response );
			return false;
		}
		return $response;
	}

	// Deprecated functions.
	/**
	 * Deprecated function, adds support for the get_order function.
	 *
	 * @return object
	 */
	public function get_order() {
		wc_deprecated_function( 'get_order', '2.0.0', 'get_klarna_order' );
		$klarna_order = $this->get_klarna_order( WC()->session->get( 'kco_wc_order_id' ) );

		// Make Klarna order an object.
		$klarna_order = json_decode( wp_json_encode( $klarna_order ) );
		return $klarna_order;
	}

	/**
	 * Deprecated function, adds support for the request_pre_get_order function.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 * @param  string $order_id WooCommerce order ID. Passed if request happens after WooCommerce has been created.
	 * @return object
	 */
	public function request_pre_get_order( $klarna_order_id, $order_id = null ) {
		wc_deprecated_function( 'request_pre_get_order', '2.0.0', 'get_klarna_order' );
		$klarna_order = $this->get_klarna_order( $klarna_order_id );

		// Make Klarna order an object.
		$klarna_order = json_decode( wp_json_encode( $klarna_order ) );
		return $klarna_order;
	}

	/**
	 * Deprecated function, adds support for the request_post_get_order function.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 * @param  string $order_id WooCommerce order ID. Passed if request happens after WooCommerce has been created.
	 * @return object
	 */
	public function request_post_get_order( $klarna_order_id, $order_id = null ) {
		wc_deprecated_function( 'request_post_get_order', '2.0.0', 'get_klarna_order' );
		$klarna_order = $this->get_klarna_order( $klarna_order_id );

		// Make Klarna order an object.
		$klarna_order = json_decode( wp_json_encode( $klarna_order ) );
		return $klarna_order;
	}

	/**
	 * Deprecated function, adds support for the request_pre_create_order function.
	 *
	 * @return object
	 */
	public function request_pre_create_order() {
		wc_deprecated_function( 'request_post_get_order', '2.0.0', 'create_klarna_order' );
		$klarna_order = $this->create_klarna_order();

		// Make Klarna order an object.
		$klarna_order = json_decode( wp_json_encode( $klarna_order ) );
		return $klarna_order;
	}

	/**
	 * Deprecated function, adds support for the request_pre_update_order function.
	 *
	 * @return object
	 */
	public function request_pre_update_order() {
		wc_deprecated_function( 'request_pre_update_order', '2.0.0', 'update_klarna_order' );
		$klarna_order = $this->create_klarna_order();

		// Make Klarna order an object.
		$klarna_order = json_decode( wp_json_encode( $klarna_order ) );
		return $klarna_order;
	}

	/**
	 * Deprecated function, adds support for the get_snippet function.
	 *
	 * @param array $klarna_order The Klarna checkout order.
	 * @return object
	 */
	public function get_snippet( $klarna_order ) {
		// Make Klarna order an object.
		$klarna_order = json_decode( wp_json_encode( $klarna_order ) );
		if ( ! is_wp_error( $klarna_order ) ) {
			return $klarna_order->html_snippet;
		}
		return $klarna_order->get_error_message();
	}
}
