<?php
/**
 * Upsell a Kustom Order
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
	 * @param string $klarna_order_id The Kustom order id.
	 * @param string $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The unique id for the upsell request.
	 * @return array
	 */
	public function request( $klarna_order_id, $order_id, $upsell_uuid ) {
		$request_url = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id . '/authorization';

		/**
		 * Filters the request arguments for a Kustom order management request, such as acknowledging an order.
		 *
		 * @param array $request_args The request arguments passed to wp_remote_request().
		 */
		$request_args = apply_filters( 'kco_wc_acknowledge_order', $this->get_request_args( $order_id, $upsell_uuid, $request_url ) );

		/**
		 * Filters the request body sent to Kustom when creating or updating an order.
		 *
		 * @link https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#modify-order-data-sent-to-kustom Modify order data sent to Kustom
		 * @link https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#anonymize-product-names-sent-to-kustom Anonymize product names sent to Kustom
		 * @link https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#set-a-forced-purchase-country Set a forced purchase country
		 * @link https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#only-accept-purchases-from-customer-over-18-years-of-age Only accept purchases from customer over 18 years of age
		 * @param array  $body        The request body.
		 * @param int    $order_id    The WooCommerce order ID.
		 * @param string $upsell_uuid The unique ID of the upsell request.
		 */
		$body_array           = apply_filters( 'kco_wc_api_request_args', json_decode( $request_args['body'], true ), $order_id, $upsell_uuid );
		$request_args['body'] = wp_json_encode( $body_array );

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
			'description'  => __( 'Upsell from thankyou page', 'klarna-upsell-for-woocommerce' ), // phpcs:ignore
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The unique id for the upsell request.
	 * @param string $url The request URL.
	 * @return array
	 */
	protected function get_request_args( $order_id, $upsell_uuid, $url = '' ) {
		/**
		 * Filters the timeout, in seconds, for requests to the Kustom API.
		 *
		 * @param int $timeout The request timeout in seconds. Default 10.
		 */
		$timeout = apply_filters( 'kco_wc_request_timeout', 10 );

		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent( $url ),
			'method'     => 'PATCH',
			'body'       => wp_json_encode( $this->get_body( $order_id, $upsell_uuid ) ),
			'timeout'    => $timeout,
		);
	}

	/**
	 * Checks response for any error. We might need this to handle special cases with headers.
	 *
	 * @TODO follow up with Kustom on how a rejected message looks.
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
