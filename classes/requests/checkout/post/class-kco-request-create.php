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
class KCO_Request_Create extends KCO_Request {
	/**
	 * Makes the request.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $checkout_flow Embedded in checkout page or redirect via Klarna HPP.
	 * @return array
	 */
	public function request( $order_id = null, $checkout_flow = 'embedded' ) {
		$request_url       = $this->get_api_url_base() . 'checkout/v3/orders';
		$request_args      = apply_filters( 'kco_wc_create_order', $this->get_request_args( $order_id, $checkout_flow ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );

		$klarna_order_id = is_wp_error( $formated_response ) ? null : $formated_response['order_id'];

		// Log the request.
		$log = KCO_Logger::format_log( $klarna_order_id, 'POST', 'KCO create order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code, $request_url );
		KCO_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $checkout_flow Embedded in checkout page or redirect via Klarna HPP.
	 * @return array
	 */
	public function get_body( $order_id, $checkout_flow ) {
		$request_options = new KCO_Request_Options();

		$request_body = array(
			'purchase_country'   => $this->get_purchase_country(),
			'locale'             => apply_filters( 'kco_locale', substr( str_replace( '_', '-', get_locale() ), 0, 5 ) ),
			'merchant_urls'      => KCO_WC()->merchant_urls->get_urls( $order_id ),
			'billing_countries'  => KCO_Request_Countries::get_billing_countries(),
			'shipping_countries' => KCO_Request_Countries::get_shipping_countries(),
			'merchant_data'      => KCO_Request_Merchant_Data::get_merchant_data(),
			'options'            => $request_options->get_options( $checkout_flow ),
			'customer'           => array(
				'type' => ( in_array( $this->settings['allowed_customer_types'], array( 'B2B', 'B2BC' ), true ) ) ? 'organization' : 'person',
			),
		);

		if ( empty( $order_id ) ) {
			// If no order id, get order data from the cart.
			$cart_data = new KCO_Request_Cart();
			$cart_data->process_data();

			$request_body['purchase_currency'] = get_woocommerce_currency();
			$request_body['order_amount']      = $cart_data->get_order_amount();
			$request_body['order_lines']       = $cart_data->get_order_lines();
			$request_body['order_tax_amount']  = $cart_data->get_order_tax_amount( $cart_data->get_order_lines() );

			if ( kco_wc_prefill_allowed() ) {
				$billing_address  = self::get_billing_address_from_customer();
				$shipping_address = self::get_shipping_address_from_customer();

				if ( ! empty( $billing_address ) ) {
					$request_body['billing_address'] = $billing_address;
				}

				if ( 'yes' === $this->settings['allow_separate_shipping'] ) {
					if ( ! empty( $shipping_address ) ) {
						$request_body['shipping_address '] = $shipping_address;
					}
				} else {
					if ( ! empty( $billing_address ) ) {
						$request_body['shipping_address'] = $billing_address;
					}
				}
			}
		} else {
			// Else get it from the order.
			$order_data = new KCO_Request_Order();
			$order      = wc_get_order( $order_id );

			$request_body['purchase_currency'] = $order->get_currency();
			$request_body['order_amount']      = $order_data->get_order_amount( $order_id );
			$request_body['order_lines']       = $order_data->get_order_lines( $order_id );
			$request_body['order_tax_amount']  = $order_data->get_total_tax( $order_id );

			$billing_address  = self::get_billing_address_from_order( $order );
			$shipping_address = self::get_shipping_address_from_order( $order );

			if ( ! empty( $billing_address ) ) {
				$request_body['billing_address'] = $billing_address;
			}

			if ( ! empty( $shipping_address ) ) {
				$request_body['shipping_address '] = $shipping_address;
			}
		}

		if ( ( array_key_exists( 'shipping_methods_in_iframe', $this->settings ) && 'yes' === $this->settings['shipping_methods_in_iframe'] ) && WC()->cart->needs_shipping() && 'embedded' === $checkout_flow ) {
			$request_body['shipping_options'] = KCO_Request_Shipping_Options::get_shipping_options( $this->separate_sales_tax );
		}

		return $request_body;
	}

	/**
	 * Gets customer billing address from checkout.
	 *
	 * @return array
	 */
	public static function get_billing_address_from_customer() {

		$address = array();

		if ( WC()->checkout()->get_value( 'billing_first_name' ) ) {
			$address['given_name'] = WC()->checkout()->get_value( 'billing_first_name' );
		}

		if ( WC()->checkout()->get_value( 'billing_last_name' ) ) {
			$address['family_name'] = WC()->checkout()->get_value( 'billing_last_name' );
		}

		if ( WC()->checkout()->get_value( 'billing_company' ) ) {
			$address['organization_name'] = WC()->checkout()->get_value( 'billing_company' );
		}

		if ( WC()->checkout()->get_value( 'billing_address_1' ) ) {
			$address['street_address'] = WC()->checkout()->get_value( 'billing_address_1' );
		}

		if ( WC()->checkout()->get_value( 'billing_address_2' ) ) {
			$address['street_address2'] = WC()->checkout()->get_value( 'billing_address_2' );
		}

		if ( WC()->checkout()->get_value( 'billing_city' ) ) {
			$address['city'] = WC()->checkout()->get_value( 'billing_city' );
		}

		if ( WC()->checkout()->get_value( 'billing_state' ) ) {
			$address['region'] = WC()->checkout()->get_value( 'billing_state' );
		}

		if ( WC()->checkout()->get_value( 'billing_country' ) ) {
			$address['country'] = WC()->checkout()->get_value( 'billing_country' );
		}

		if ( WC()->checkout()->get_value( 'billing_email' ) ) {
			$address['email'] = WC()->checkout()->get_value( 'billing_email' );
		}

		if ( WC()->checkout()->get_value( 'billing_phone' ) ) {
			$address['phone'] = WC()->checkout()->get_value( 'billing_phone' );
		}

		if ( ! empty( WC()->checkout()->get_value( 'billing_postcode' ) ) ) {
			$postal_code            = str_replace( ' ', '', WC()->checkout()->get_value( 'billing_postcode' ) );
			$address['postal_code'] = $postal_code;
		}

		return $address;
	}

	/**
	 * Gets customer shipping address from checkout.
	 *
	 * @return array
	 */
	public static function get_shipping_address_from_customer() {

		$address = array();

		if ( WC()->checkout()->get_value( 'shipping_first_name' ) ) {
			$address['given_name'] = WC()->checkout()->get_value( 'shipping_first_name' );
		}

		if ( WC()->checkout()->get_value( 'shipping_last_name' ) ) {
			$address['family_name'] = WC()->checkout()->get_value( 'shipping_last_name' );
		}

		if ( WC()->checkout()->get_value( 'shipping_company' ) ) {
			$address['organization_name'] = WC()->checkout()->get_value( 'shipping_company' );
		}

		if ( WC()->checkout()->get_value( 'shipping_address_1' ) ) {
			$address['street_address'] = WC()->checkout()->get_value( 'shipping_address_1' );
		}

		if ( WC()->checkout()->get_value( 'shipping_address_2' ) ) {
			$address['street_address2'] = WC()->checkout()->get_value( 'shipping_address_2' );
		}

		if ( WC()->checkout()->get_value( 'shipping_city' ) ) {
			$address['city'] = WC()->checkout()->get_value( 'shipping_city' );
		}

		if ( WC()->checkout()->get_value( 'shipping_state' ) ) {
			$address['region'] = WC()->checkout()->get_value( 'shipping_state' );
		}

		if ( WC()->checkout()->get_value( 'shipping_country' ) ) {
			$address['country'] = WC()->checkout()->get_value( 'shipping_country' );
		}

		if ( ! empty( WC()->checkout()->get_value( 'shipping_email' ) ) ) {
			$shipping_email = WC()->checkout()->get_value( 'shipping_email' );
		} else {
			$shipping_email = WC()->checkout()->get_value( 'billing_email' );
		}
		if ( $shipping_email ) {
			$address['email'] = $shipping_email;
		}

		if ( ! empty( WC()->checkout()->get_value( 'shipping_phone' ) ) ) {
			$shipping_phone = WC()->checkout()->get_value( 'shipping_phone' );
		} else {
			$shipping_phone = WC()->checkout()->get_value( 'billing_phone' );
		}
		if ( $shipping_phone ) {
			$address['phone'] = $shipping_phone;
		}

		if ( ! empty( WC()->checkout()->get_value( 'shipping_postcode' ) ) ) {
			$postal_code            = str_replace( ' ', '', WC()->checkout()->get_value( 'shipping_postcode' ) );
			$address['postal_code'] = $postal_code;
		}

		return $address;
	}

	/**
	 * Gets customer billing address from order.
	 *
	 * @param object $order The WooCommerce order.
	 * @return array
	 */
	public static function get_billing_address_from_order( $order ) {
		$address = array();

		if ( $order->get_billing_first_name() ) {
			$address['given_name'] = $order->get_billing_first_name();
		}

		if ( $order->get_billing_last_name() ) {
			$address['family_name'] = $order->get_billing_last_name();
		}

		if ( $order->get_billing_company() ) {
			$address['organization_name'] = $order->get_billing_company();
		}

		if ( $order->get_billing_address_1() ) {
			$address['street_address'] = $order->get_billing_address_1();
		}

		if ( $order->get_billing_address_2() ) {
			$address['street_address2'] = $order->get_billing_address_2();
		}

		if ( $order->get_billing_city() ) {
			$address['city'] = $order->get_billing_city();
		}

		if ( $order->get_billing_state() ) {
			$address['region'] = $order->get_billing_state();
		}

		if ( $order->get_billing_country() ) {
			$address['country'] = $order->get_billing_country();
		}

		if ( $order->get_billing_email() ) {
			$address['email'] = $order->get_billing_email();
		}

		if ( $order->get_billing_phone() ) {
			$address['phone'] = $order->get_billing_phone();
		}

		if ( ! empty( $order->get_billing_postcode() ) ) {
			$postal_code            = str_replace( ' ', '', $order->get_billing_postcode() );
			$address['postal_code'] = $postal_code;
		}

		return $address;
	}

	/**
	 * Gets customer shipping address from order.
	 *
	 * @param object $order The WooCommerce order.
	 * @return array
	 */
	public static function get_shipping_address_from_order( $order ) {
		$address = array();

		if ( $order->get_shipping_first_name() ) {
			$address['given_name'] = $order->get_shipping_first_name();
		}

		if ( $order->get_shipping_last_name() ) {
			$address['family_name'] = $order->get_shipping_last_name();
		}

		if ( $order->get_shipping_company() ) {
			$address['organization_name'] = $order->get_shipping_company();
		}

		if ( $order->get_shipping_address_1() ) {
			$address['street_address'] = $order->get_shipping_address_1();
		}

		if ( $order->get_shipping_address_2() ) {
			$address['street_address2'] = $order->get_shipping_address_2();
		}

		if ( $order->get_shipping_city() ) {
			$address['city'] = $order->get_shipping_city();
		}

		if ( $order->get_shipping_state() ) {
			$address['region'] = $order->get_shipping_state();
		}

		if ( $order->get_shipping_country() ) {
			$address['country'] = $order->get_shipping_country();
		}

		$shipping_email = ! empty( $order->get_meta( '_shipping_email', true ) ) ? $order->get_meta( '_shipping_email', true ) : $order->get_billing_email();
		if ( $shipping_email ) {
			$address['email'] = $shipping_email;
		}

		// NOTE: Since we declare support for WC v4+, and WC_Order::get_shipping_phone was only added in 5.6.0, we need to use get_meta instead.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '5.6.0', '>=' ) ) {
			$shipping_phone = $order->get_shipping_phone();
		} else {
			$shipping_phone = $order->get_meta( '_shipping_phone', true );
		}

		$shipping_phone = ! empty( $shipping_phone ) ? $shipping_phone : $order->get_billing_phone();
		if ( $shipping_phone ) {
			$address['phone'] = $shipping_phone;
		}

		if ( ! empty( $order->get_shipping_postcode() ) ) {
			$postal_code            = str_replace( ' ', '', $order->get_shipping_postcode() );
			$address['postal_code'] = $postal_code;
		}

		return $address;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $checkout_flow Embedded in checkout page or redirect via Klarna HPP.
	 * @return array
	 */
	protected function get_request_args( $order_id, $checkout_flow ) {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'POST',
			'body'       => wp_json_encode( apply_filters( 'kco_wc_api_request_args', $this->get_body( $order_id, $checkout_flow ), $order_id ) ),
			'timeout'    => apply_filters( 'kco_wc_request_timeout', 10 ),
		);
	}
}
