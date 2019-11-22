<?php
/**
 * Update KCO Order
 *
 * @package Klarna_Checkout/Classes/Request/Checkout/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update KCO Order
 */
class KCO_Request_Update extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $klarna_order_id The Klarna order id.
	 * @return array
	 */
	public function request( $klarna_order_id ) {
		$request_url       = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;
		$request_args      = apply_filters( 'kco_wc_update_order', $this->get_request_args() );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'POST', 'KCO create order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		$cart_data = new KCO_Request_Cart();
		$cart_data->process_data();

		$request_body = array(
			'purchase_country'   => $this->get_purchase_country(),
			'purchase_currency'  => get_woocommerce_currency(),
			'locale'             => substr( str_replace( '_', '-', get_locale() ), 0, 5 ),
			'merchant_urls'      => KCO_WC()->merchant_urls->get_urls(),
			'order_amount'       => $cart_data->get_order_amount(),
			'order_lines'        => $cart_data->get_order_lines(),
			'order_tax_amount'   => $cart_data->get_order_tax_amount( $cart_data->get_order_lines() ),
			'billing_countries'  => KCO_Request_Countries::get_billing_countries(),
			'shipping_countries' => KCO_Request_Countries::get_shipping_countries(),
			'merchant_data'      => KCO_Request_Merchant_Data::get_merchant_data(),
			'options'            => KCO_Request_Options::get_options(),
		);

		if ( kco_wc_prefill_allowed() ) {
			$request_body['billing_address'] = array(
				'email'           => WC()->checkout()->get_value( 'billing_email' ),
				'postal_code'     => WC()->checkout()->get_value( 'billing_postcode' ),
				'country'         => WC()->checkout()->get_value( 'billing_country' ),
				'phone'           => WC()->checkout()->get_value( 'billing_phone' ),
				'given_name'      => WC()->checkout()->get_value( 'billing_first_name' ),
				'family_name'     => WC()->checkout()->get_value( 'billing_last_name' ),
				'street_address'  => WC()->checkout()->get_value( 'billing_address_1' ),
				'street_address2' => WC()->checkout()->get_value( 'billing_address_2' ),
				'city'            => WC()->checkout()->get_value( 'billing_city' ),
				'region'          => WC()->checkout()->get_value( 'billing_state' ),
			);
		}

		if ( ( array_key_exists( 'shipping_methods_in_iframe', $this->settings ) && 'yes' === $this->settings['shipping_methods_in_iframe'] ) && WC()->cart->needs_shipping() ) {
			$request_body['shipping_options'] = KCO_Request_Shipping_Options::get_shipping_options();
		}

		return $request_body;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	protected function get_request_args() {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'POST',
			'body'       => wp_json_encode( $this->get_body() ),
		);
	}
}
