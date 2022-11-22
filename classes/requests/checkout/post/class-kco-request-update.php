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
	 * @param int    $order_id The WooCommerce order id.
	 * @param bool   $force If true always update the order, even if not needed.
	 * @return array
	 */
	public function request( $klarna_order_id, $order_id = null, $force = false ) {
		$request_url  = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;
		$request_args = apply_filters( 'kco_wc_update_order', $this->get_request_args( $order_id ) );

		// Check if we need to update.
		if ( WC()->session->get( 'kco_update_md5' ) && WC()->session->get( 'kco_update_md5' ) === md5( wp_json_encode( $request_args ) ) && ! $force ) {
			return false;
		}
		WC()->session->set( 'kco_update_md5', md5( wp_json_encode( $request_args ) ) );

		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'POST', 'KCO update order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_body( $order_id ) {
		$cart_data = new KCO_Request_Cart();
		$cart_data->process_data();

		$request_options = new KCO_Request_Options();

		$request_body = array(
			'purchase_country'   => $this->get_purchase_country(),
			'purchase_currency'  => get_woocommerce_currency(),
			'locale'             => apply_filters( 'kco_locale', substr( str_replace( '_', '-', get_locale() ), 0, 5 ) ),
			'merchant_urls'      => KCO_WC()->merchant_urls->get_urls( $order_id ),
			'order_amount'       => $cart_data->get_order_amount(),
			'order_lines'        => $cart_data->get_order_lines(),
			'order_tax_amount'   => $cart_data->get_order_tax_amount( $cart_data->get_order_lines() ),
			'billing_countries'  => KCO_Request_Countries::get_billing_countries(),
			'shipping_countries' => KCO_Request_Countries::get_shipping_countries(),
			'merchant_data'      => KCO_Request_Merchant_Data::get_merchant_data(),
			'options'            => $request_options->get_options(),
		);

		// If we have an order id, set the merchant references.
		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( $order_id );
			// Set the merchant references to the order.
			$request_body['merchant_reference1'] = $order->get_order_number();
			$request_body['merchant_reference2'] = $order_id;
		}

		if ( kco_wc_prefill_allowed() ) {
			$request_body['billing_address'] = array(
				'email'             => WC()->checkout()->get_value( 'billing_email' ),
				'postal_code'       => WC()->checkout()->get_value( 'billing_postcode' ),
				'country'           => WC()->checkout()->get_value( 'billing_country' ),
				'phone'             => WC()->checkout()->get_value( 'billing_phone' ),
				'given_name'        => WC()->checkout()->get_value( 'billing_first_name' ),
				'family_name'       => WC()->checkout()->get_value( 'billing_last_name' ),
				'organization_name' => WC()->checkout()->get_value( 'billing_company' ),
				'street_address'    => WC()->checkout()->get_value( 'billing_address_1' ),
				'street_address2'   => WC()->checkout()->get_value( 'billing_address_2' ),
				'city'              => WC()->checkout()->get_value( 'billing_city' ),
				'region'            => WC()->checkout()->get_value( 'billing_state' ),
			);

			$request_body['shipping_address'] = array(
				'postal_code'       => WC()->checkout()->get_value( 'shipping_postcode' ),
				'country'           => WC()->checkout()->get_value( 'shipping_country' ),
				'given_name'        => WC()->checkout()->get_value( 'shipping_first_name' ),
				'family_name'       => WC()->checkout()->get_value( 'shipping_last_name' ),
				'organization_name' => WC()->checkout()->get_value( 'shipping_company' ),
				'street_address'    => WC()->checkout()->get_value( 'shipping_address_1' ),
				'street_address2'   => WC()->checkout()->get_value( 'shipping_address_2' ),
				'city'              => WC()->checkout()->get_value( 'shipping_city' ),
				'region'            => WC()->checkout()->get_value( 'shipping_state' ),
			);

			$request_body['shipping_address'] = wp_parse_args( $request_body['billing_address'], $request_body['shipping_address'] ?? array() );
		}

		if ( ( array_key_exists( 'shipping_methods_in_iframe', $this->settings ) && 'yes' === $this->settings['shipping_methods_in_iframe'] ) && WC()->cart->needs_shipping() ) {
			$request_body['shipping_options'] = KCO_Request_Shipping_Options::get_shipping_options( $this->separate_sales_tax );
		} elseif ( ! WC()->cart->needs_shipping() ) {
			// If the order had a shipping option before but is removed now, same needs to be sent to klarna else it will retain the old shipping option.
			$request_body['shipping_options'] = array();
		}

		return $request_body;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	protected function get_request_args( $order_id ) {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'POST',
			'body'       => wp_json_encode( apply_filters( 'kco_wc_api_request_args', $this->get_body( $order_id ), $order_id ) ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}
}
