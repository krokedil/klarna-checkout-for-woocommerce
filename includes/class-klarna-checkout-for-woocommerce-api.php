<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_API class.
 *
 * Class that talks to KCO API, wrapper for V2 and V3.
 */
class Klarna_Checkout_For_WooCommerce_API {

	/**
	 * Klarna Checkout for WooCommerce settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Klarna_Checkout_For_WooCommerce_API constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_kco_settings' );
	}

	/**
	 * Creates Klarna Checkout order.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function request_pre_create_order() {
		$request_url  = $this->get_api_url_base() . 'checkout/v3/orders';
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'body'       => $this->get_request_body( 'create' ),
		);
		$log_array    = array(
			'headers'    => $request_args['headers'],
			'user-agent' => $request_args['user-agent'],
			'body'       => json_decode( $request_args['body'] ),
		);
		KCO_WC()->logger->log( 'Create Klarna order (' . $request_url . ') ' . stripslashes_deep( json_encode( $request_args ) ) );
		krokedil_log_events( null, 'Pre Create Order request args', $log_array );
		$response = wp_safe_remote_post( $request_url, $request_args );

		// If request is_wp_error() redirect customer to cart page and display the error message.
		if ( is_wp_error( $response ) ) {
			$error = $this->extract_error_messages( $response );
			KCO_WC()->logger->log( 'Create Klarna order ERROR (' . stripslashes_deep( json_encode( $error ) ) . ') ' . stripslashes_deep( json_encode( $response ) ) );
			// return $error.
			$url = add_query_arg(
				array(
					'kco-order' => 'error',
					'reason'    => base64_encode( $error->get_error_message() ),
				),
				wc_get_cart_url()
			);
			wp_safe_redirect( $url );
			exit;
		}

		if ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) {
			// All good. Return Klarna order.
			$klarna_order = json_decode( $response['body'] );
			$this->save_order_id_to_session( sanitize_key( $klarna_order->order_id ) );
			$this->save_order_api_to_session( $klarna_order );
			$log_order               = clone $klarna_order;
			$log_order->html_snippet = '';
			krokedil_log_events( null, 'Pre Create Order response', $log_order );
			return $response;
		} elseif ( 405 === $response['response']['code'] || 401 === $response['response']['code'] ) {
			// 405 or 401 response from Klarna. Redirect customer to cart page and display the error message.
			$error = $response['response']['message'];
			KCO_WC()->logger->log( 'Create Klarna order ERROR (' . $error . ') ' . stripslashes_deep( json_encode( $response ) ) );
			// return $error;
			// Redirect customer to cart page.
			$url = add_query_arg(
				array(
					'kco-order' => 'error',
					'reason'    => base64_encode( $error ),
				),
				wc_get_cart_url()
			);
			wp_safe_redirect( $url );
			exit;
		} else {
			// Something else was wrong in the request. Redirect customer to cart page and display the error message.
			$error = $this->extract_error_messages( $response );
			KCO_WC()->logger->log( 'Create Klarna order ERROR (' . stripslashes_deep( json_encode( $error ) ) . ') ' . stripslashes_deep( json_encode( $response ) ) );
			krokedil_log_events( null, 'Pre Create Order response', $error );

			// Redirect customer to cart page.
			$url = add_query_arg(
				array(
					'kco-order' => 'error',
					'reason'    => base64_encode( $error->get_error_message() ),
				),
				wc_get_cart_url()
			);
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Retrieve ongoing Klarna order.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 * @param  string $order_id WooCommerce order ID. Passed if request happens after WooCommerce has been created.
	 *
	 * @return object $klarna_order    Klarna order.
	 */
	public function request_pre_get_order( $klarna_order_id, $order_id = null ) {
		$request_url  = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
		);
		krokedil_log_events( $order_id, 'Pre Retrieve Order request args', $request_args );
		KCO_WC()->logger->log( 'Retrieve ongoing Klarna order (' . $request_url . ') ' . json_encode( $request_args ) );

		$response = wp_safe_remote_get( $request_url, $request_args );

		if ( ! is_wp_error( $response ) && ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) ) {
			$klarna_order            = $response;
			$log_order               = json_decode( $response['body'] );
			$log_order               = clone $log_order;
			$log_order->html_snippet = '';
			krokedil_log_events( $order_id, 'Pre Retrieve Order response', $log_order );
			return $klarna_order;
		} else {
			$error = $this->extract_error_messages( $response );
			krokedil_log_events( $order_id, 'Pre Retrieve Order response', $error );
			return $error;
		}
	}

	/**
	 * Update ongoing Klarna order.
	 *
	 * @return object $klarna_order Klarna order.
	 */
	public function request_pre_update_order() {
		$klarna_order_id = $this->get_order_id_from_session();
		$request_url     = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;
		$request_args    = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'body'       => $this->get_request_body(),
		);
		$log_array       = array(
			'headers'    => $request_args['headers'],
			'user-agent' => $request_args['user-agent'],
			'body'       => json_decode( $request_args['body'] ),
		);
		// No update if nothing changed in data being sent to Klarna.
		if ( WC()->session->get( 'kco_wc_update_md5' ) && WC()->session->get( 'kco_wc_update_md5' ) === md5( serialize( $request_args ) ) ) {
			return;
		}
		krokedil_log_events( null, 'Pre Update Order request args', $log_array );
		KCO_WC()->logger->log( 'Update ongoing Klarna order (' . $request_url . ') ' . json_encode( $request_args ) );

		$response = wp_safe_remote_post( $request_url, $request_args );

		if ( ! is_wp_error( $response ) && ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) ) {
			WC()->session->set( 'kco_wc_update_md5', md5( serialize( $request_args ) ) );

			$klarna_order            = json_decode( $response['body'] );
			$log_order               = clone $klarna_order;
			$log_order->html_snippet = '';
			krokedil_log_events( null, 'Pre Update Order response', $log_order );
			return $klarna_order;
		} else {
			WC()->session->__unset( 'kco_wc_update_md5' );
			WC()->session->__unset( 'kco_wc_order_id' );
			$error = $this->extract_error_messages( $response );
			krokedil_log_events( null, 'Pre Update Order response', $error );
			return $error;
		}

	}


	/**
	 * Acknowledges Klarna Checkout order.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 * @param  string $order_id WooCommerce order ID. Passed if request happens after WooCommerce has been created.
	 *
	 * @return WP_Error|array $response
	 */
	public function request_post_get_order( $klarna_order_id, $order_id = null ) {
		$request_url  = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id;
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
		);
		$response     = wp_safe_remote_get( $request_url, $request_args );

		if ( ! is_wp_error( $response ) && ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) ) {
			krokedil_log_events( $order_id, 'Post Get Order response', stripslashes_deep( json_decode( $response['body'] ) ) );
			KCO_WC()->logger->log( 'Post Get Order response (' . $request_url . ') ' . stripslashes_deep( json_encode( $response ) ) );
			return $response;
		} elseif ( ! is_wp_error( $response ) && ( 401 === $response['response']['code'] || 404 === $response['response']['code'] || 405 === $response['response']['code'] ) ) {
			krokedil_log_events( $order_id, 'ERROR Post Get Order response', $response );
			KCO_WC()->logger->log( 'ERROR Post Get Order response (' . $request_url . ') ' . stripslashes_deep( json_encode( $response ) ) );
			$error_message = $response['response']['message'];
			$error         = new WP_Error();
			$error->add( 'kco', $error_message );
			return $error;
		} else {
			$error = $this->extract_error_messages( $response );
			krokedil_log_events( $order_id, 'ERROR Post Get Order response', $response );
			KCO_WC()->logger->log( 'ERROR Post Get Order response (' . $request_url . ') ' . stripslashes_deep( json_encode( $response ) ) );
			return $error;
		}
	}


	/**
	 * Acknowledges Klarna Checkout order.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 *
	 * @return WP_Error|array $response
	 */
	public function request_post_acknowledge_order( $klarna_order_id ) {
		$request_url  = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id . '/acknowledge';
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
		);

		$response = wp_safe_remote_post( $request_url, $request_args );

		return $response;
	}

	/**
	 * Adds WooCommerce order ID to Klarna order as merchant_reference. And clear Klarna order ID value from WC session.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 * @param  array  $merchant_references Array of merchant references.
	 *
	 * @return WP_Error|array $response
	 */
	public function request_post_set_merchant_reference( $klarna_order_id, $merchant_references ) {
		$request_url  = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id . '/merchant-references';
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'PATCH',
			'body'       => wp_json_encode(
				array(
					'merchant_reference1' => $merchant_references['merchant_reference1'],
					'merchant_reference2' => $merchant_references['merchant_reference2'],
				)
			),
		);

		$response = wp_safe_remote_request( $request_url, $request_args );

		return $response;
	}

	/**
	 * Gets Klarna API URL base.
	 */
	public function get_api_url_base() {
		$base_location  = wc_get_base_location();
		$country_string = 'US' === $base_location['country'] ? '-na' : '';
		$test_string    = 'yes' === $this->settings['testmode'] ? '.playground' : '';

		return 'https://api' . $country_string . $test_string . '.klarna.com/';
	}

	/**
	 * Gets Klarna order from WC_Session
	 *
	 * @return array|string
	 */
	public function get_order_id_from_session() {
		return WC()->session->get( 'kco_wc_order_id' );
	}

	/**
	 * Saves Klarna order ID to WooCommerce session.
	 *
	 * @param string $order_id Klarna order ID.
	 */
	public function save_order_id_to_session( $order_id ) {
		WC()->session->set( 'kco_wc_order_id', $order_id );
	}

	/**
	 * Saves Klarna order API to WooCommerce session.
	 *
	 * 'na' or 'eu', used for order updates, to avoid trying to switch API when updating KCO Global orders.
	 *
	 * @param string $klarna_order Klarna order.
	 */
	public function save_order_api_to_session() {
		if ( 'US' === $this->get_purchase_country() ) {
			$api = 'US';
		} else {
			$api = 'EU';
		}

		WC()->session->set( 'kco_wc_order_api', $api );
	}

	/**
	 * Gets Klarna Checkout order.
	 *
	 * If WC_Session value for Klarna order ID exists, attempt to retrieve that order.
	 * If this fails, create a new one and retrieve it.
	 * If WC_Session value for Klarna order ID does not exist, create a new order and retrieve it.
	 *
	 * @return Klarna_Checkout_Order $order Klarna order.
	 */
	public function get_order() {
		$order_id = $this->get_order_id_from_session();

		if ( $order_id ) {
			// Get Klarna order.
			$response = $this->request_pre_get_order( $order_id );

			// If we got errors - delete the Klarna order id session and return the error object.
			if ( is_wp_error( $response ) ) {
				WC()->session->__unset( 'kco_wc_order_id' );
				return $response;
			}

			// Check that we got a response body.
			if ( ! empty( $response['body'] ) ) {
				$order = json_decode( $response['body'] );
				return $order;
			}
		} else {
			// Abort if we're on the thankyou page.
			if ( is_order_received_page() ) {
				return;
			}

			// Create new Klarna order.
			$response = $this->request_pre_create_order();

			// Check if we got errors. Return the error object if something is wrong.
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// Check that we got a response body.
			if ( ! empty( $response['body'] ) ) {
				$order = json_decode( $response['body'] );
				return $order;
			}
		}

		// Something went wrong, delete the Klarna order id from session and redirect user to cart page.
		WC()->session->__unset( 'kco_wc_order_id' );
		$url = add_query_arg(
			array(
				'kco-order' => 'error',
				'reason'    => base64_encode( __( 'The Klarna session has expired. Please try again', 'klarna-checkout-for-woocommerce' ) ),
			),
			wc_get_cart_url()
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Gets KCO iframe snippet from KCO order.
	 *
	 * @param Klarna_Order $order Klarna Checkout order.
	 *
	 * @return mixed
	 */
	public function get_snippet( $order ) {
		if ( ! is_wp_error( $order ) ) {
			$this->maybe_clear_session_values( $order );

			return $order->html_snippet;
		}

		return $order->get_error_message();
	}

	/**
	 * Clear WooCommerce session values if Klarna Checkout order is completed.
	 *
	 * @param Klarna_Order $order Klarna Checkout order.
	 */
	public function maybe_clear_session_values( $order ) {
		if ( 'checkout_complete' === $order->status ) {
			WC()->session->__unset( 'kco_wc_update_md5' );
			WC()->session->__unset( 'kco_wc_order_id' );
			WC()->session->__unset( 'kco_wc_order_notes' );
			WC()->session->__unset( 'kco_wc_order_api' );
			WC()->session->__unset( 'kco_wc_extra_fields_values' );
			WC()->session->__unset( 'kco_wc_prefill_consent' );
			WC()->session->__unset( 'kco_checkout_form' );
		}
	}

	/**
	 * Gets Klarna merchant ID.
	 *
	 * @return string
	 */
	public function get_merchant_id() {
		$credentials = KCO_WC()->credentials->get_credentials_from_session();

		return $credentials['merchant_id'];
	}

	/**
	 * Gets Klarna shared secret.
	 *
	 * @return string
	 */
	public function get_shared_secret() {
		$credentials = KCO_WC()->credentials->get_credentials_from_session();

		return $credentials['shared_secret'];
	}

	/**
	 * Gets country for Klarna purchase.
	 *
	 * @return string
	 */
	public function get_purchase_country() {
		$base_location = wc_get_base_location();
		$country       = $base_location['country'];

		return $country;
	}

	/**
	 * Gets currency for Klarna purchase.
	 *
	 * @return string
	 */
	public function get_purchase_currency() {
		return get_woocommerce_currency();
	}

	/**
	 * Gets locale for Klarna purchase.
	 *
	 * @return string
	 */
	public function get_purchase_locale() {

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$locale = ICL_LANGUAGE_CODE;
		} else {
			$locale = explode( '_', get_locale() );
			$locale = $locale[0];
		}

		switch ( WC()->checkout()->get_value( 'billing_country' ) ) {
			case 'AT':
				if ( 'de' == $locale ) {
					$klarna_locale = 'de-at';
				} else {
					$klarna_locale = 'en-at';
				}
				break;
			case 'DE':
				if ( 'de' == $locale ) {
					$klarna_locale = 'de-de';
				} else {
					$klarna_locale = 'en-de';
				}
				break;
			case 'DK':
				if ( 'da' == $locale ) {
					$klarna_locale = 'da-dk';
				} else {
					$klarna_locale = 'en-dk';
				}
				break;
			case 'FI':
				if ( 'fi' == $locale ) {
					$klarna_locale = 'fi-fi';
				} elseif ( 'sv' == $locale ) {
					$klarna_locale = 'sv-fi';
				} else {
					$klarna_locale = 'en-fi';
				}
				break;
			case 'NL':
				if ( 'nl' == $locale ) {
					$klarna_locale = 'nl-nl';
				} else {
					$klarna_locale = 'en-nl';
				}
				break;
			case 'NO':
				if ( 'nb' == $locale || 'nn' == $locale || 'no' == $locale ) {
					$klarna_locale = 'nb-no';
				} else {
					$klarna_locale = 'en-no';
				}
				break;
			case 'SE':
				if ( 'sv' == $locale || 'sv_se' == $locale ) {
					$klarna_locale = 'sv-se';
				} else {
					$klarna_locale = 'en-se';
				}
				break;
			case 'GB':
				$klarna_locale = 'en-gb';
				break;
			case 'US':
				$klarna_locale = 'en-us';
				break;
			default:
				$klarna_locale = 'en-us';
		} // End switch().

		return $klarna_locale;
	}

	/**
	 * Gets merchant URLs for Klarna purchase.
	 *
	 * @return array
	 */
	public function get_merchant_urls() {
		return KCO_WC()->merchant_urls->get_urls();
	}

	/**
	 * Gets merchant data for Klarna purchase.
	 *
	 * @return array
	 */
	public function get_merchant_data() {
		$merchant_data = array();

		// Coupon info.
		foreach ( WC()->cart->get_applied_coupons() as $coupon ) {
			$merchant_data['coupons'][] = $coupon;
		}

		// Is user logged in.
		$merchant_data['is_user_logged_in'] = is_user_logged_in();

		// Cart hash.
		// $cart_hash                  = md5( wp_json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
		// $merchant_data['cart_hash'] = $cart_hash;
		return json_encode( $merchant_data );
	}

	/**
	 * Gets Klarna API request headers.
	 *
	 * @return array
	 */
	public function get_request_headers() {
		$request_headers = array(
			'Authorization' => 'Basic ' . base64_encode( $this->get_merchant_id() . ':' . $this->get_shared_secret() ),
			'Content-Type'  => 'application/json',
		);

		return $request_headers;
	}

	/**
	 * Gets Klarna API request headers.
	 *
	 * @return string
	 */
	public function get_user_agent() {
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - KCO:' . KCO_WC_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';

		return $user_agent;
	}

	/**
	 * Gets Klarna API request body.
	 *
	 * @TODO: Clean this up by using maybe_add functions.
	 *
	 * @param  string $request_type Type of request.
	 *
	 * @return false|string
	 */
	public function get_request_body( $request_type = null ) {
		KCO_WC()->order_lines->process_data();

		$order_lines      = KCO_WC()->order_lines->get_order_lines();
		$order_tax_amount = KCO_WC()->order_lines->get_order_tax_amount( $order_lines );
		$order_amount     = KCO_WC()->order_lines->get_order_amount();

		if ( $order_amount !== KCO_WC()->order_lines->get_order_lines_total_amount( $order_lines ) ) {
			$order_lines = KCO_WC()->order_lines->adjust_order_lines( $order_lines );
		}

		$request_args = array(
			'purchase_country'   => $this->get_purchase_country(),
			'purchase_currency'  => $this->get_purchase_currency(),
			'locale'             => $this->get_purchase_locale(),
			'merchant_urls'      => $this->get_merchant_urls(),
			'order_amount'       => $order_amount,
			'order_tax_amount'   => $order_tax_amount,
			'order_lines'        => $order_lines,
			'shipping_countries' => $this->get_shipping_countries(),
			'merchant_data'      => $this->get_merchant_data(),
		);

		if ( kco_wc_prefill_allowed() ) {
			$request_args['billing_address'] = array(
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

		$request_args['options']['title_mandatory']                 = $this->get_title_mandatory();
		$request_args['options']                                    = array();
		$request_args['options']['allow_separate_shipping_address'] = $this->get_allow_separate_shipping_address();
		$request_args['options']['date_of_birth_mandatory']         = $this->get_dob_mandatory();
		$request_args['options']['national_identification_number_mandatory'] = $this->get_dob_mandatory();
		$request_args['options']['title_mandatory']                          = $this->get_title_mandatory();

		if ( $this->get_iframe_colors() ) {
			$request_args['options'] = array_merge( $request_args['options'], $this->get_iframe_colors() );
		}

		if ( $this->get_shipping_details() ) {
			$request_args['options']['shipping_details'] = $this->get_shipping_details();
		}

		// Allow external payment method plugin to do its thing.
		// @TODO: Extract this into a hooked function.
		if ( in_array( $this->get_purchase_country(), array( 'SE', 'NO', 'FI' ), true ) ) {
			if ( isset( $this->settings['allowed_customer_types'] ) ) {
				$customer_types_setting = $this->settings['allowed_customer_types'];

				switch ( $customer_types_setting ) {
					case 'B2B':
						$allowed_customer_types = array( 'organization' );
						$customer_type          = 'organization';
						break;
					case 'B2BC':
						$allowed_customer_types = array( 'person', 'organization' );
						$customer_type          = 'organization';
						break;
					case 'B2CB':
						$allowed_customer_types = array( 'person', 'organization' );
						$customer_type          = 'person';
						break;
					default:
						$allowed_customer_types = array( 'person' );
						$customer_type          = 'person';
				}

				$request_args['options']['allowed_customer_types'] = $allowed_customer_types;
				if ( 'create' === $request_type ) {
					$request_args['customer']['type'] = $customer_type;
				}
			}
		}
		if ( 'create' === $request_type ) {
			$request_args = apply_filters( 'kco_wc_create_order', $request_args );
		}

		$request_body = wp_json_encode( apply_filters( 'kco_wc_api_request_args', $request_args ) );
		return $request_body;
	}

	/**
	 * Gets shipping countries formatted for Klarna.
	 *
	 * @return array
	 */
	public function get_shipping_countries() {
		$wc_countries = new WC_Countries();

		return array_keys( $wc_countries->get_shipping_countries() );
	}

	/**
	 * Gets allowed separate shipping details option.
	 *
	 * @return bool
	 */
	public function get_allow_separate_shipping_address() {
		$allow_separate_shipping = array_key_exists( 'allow_separate_shipping', $this->settings ) && 'yes' === $this->settings['allow_separate_shipping'];

		return $allow_separate_shipping;
	}

	/**
	 * Gets date of birth mandatory option.
	 *
	 * @return bool
	 */
	public function get_dob_mandatory() {
		$dob_mandatory = array_key_exists( 'dob_mandatory', $this->settings ) && 'yes' === $this->settings['dob_mandatory'];

		return $dob_mandatory;
	}

	/**
	 * Gets date of birth mandatory option.
	 *
	 * @return bool
	 */
	public function get_title_mandatory() {
		$title_mandatory = array_key_exists( 'title_mandatory', $this->settings ) && 'yes' === $this->settings['title_mandatory'];

		return $title_mandatory;
	}

	/**
	 * Gets shipping details note.
	 *
	 * @return bool
	 */
	public function get_shipping_details() {
		if ( array_key_exists( 'shipping_details', $this->settings ) ) {
			return $this->settings['shipping_details'];
		}

		return false;
	}

	/**
	 * Gets iframe color settings.
	 *
	 * @return array|bool
	 */
	private function get_iframe_colors() {
		$color_settings = array();

		if ( $this->check_option_field( 'color_button' ) ) {
			$color_settings['color_button'] = self::add_hash_to_color( $this->check_option_field( 'color_button' ) );
		}

		if ( $this->check_option_field( 'color_button_text' ) ) {
			$color_settings['color_button_text'] = self::add_hash_to_color( $this->check_option_field( 'color_button_text' ) );
		}

		if ( $this->check_option_field( 'color_checkbox' ) ) {
			$color_settings['color_checkbox'] = self::add_hash_to_color( $this->check_option_field( 'color_checkbox' ) );
		}

		if ( $this->check_option_field( 'color_checkbox_checkmark' ) ) {
			$color_settings['color_checkbox_checkmark'] = self::add_hash_to_color( $this->check_option_field( 'color_checkbox_checkmark' ) );
		}

		if ( $this->check_option_field( 'color_header' ) ) {
			$color_settings['color_header'] = self::add_hash_to_color( $this->check_option_field( 'color_header' ) );
		}

		if ( $this->check_option_field( 'color_link' ) ) {
			$color_settings['color_link'] = self::add_hash_to_color( $this->check_option_field( 'color_link' ) );
		}

		if ( $this->check_option_field( 'radius_border' ) ) {
			$color_settings['radius_border'] = self::add_hash_to_color( $this->check_option_field( 'radius_border' ) );
		}

		if ( count( $color_settings ) > 0 ) {
			return $color_settings;
		}

		return false;
	}

	private static function add_hash_to_color( $hex ) {
		if ( '' != $hex ) {
			$hex = str_replace( '#', '', $hex );
			$hex = '#' . $hex;
		}
		return $hex;
	}

	private function check_option_field( $field ) {
		if ( array_key_exists( $field, $this->settings ) && '' !== $this->settings[ $field ] ) {
			return $this->settings[ $field ];
		}

		return false;
	}

	/**
	 * Extracts error messages from Klarna's response.
	 *
	 * @param mixed $response Klarna API response.
	 *
	 * @return mixed
	 */
	public function extract_error_messages( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = json_decode( $response['body'] );
		$error         = new WP_Error();

		if ( ! empty( $response_body->error_messages ) && is_array( $response_body->error_messages ) ) {
			KCO_WC()->logger->log( var_export( $response_body, true ) );

			foreach ( $response_body->error_messages as $error_message ) {
				$error->add( 'kco', $error_message );
			}
		}

		return $error;
	}

	/**
	 * Create a recurring order with Klarna
	 *
	 * @param object $order The WooCommerce order.
	 * @param string $recurring_token The Recurring token from Klarna
	 * @return void
	 */
	public function request_create_recurring_order( $order, $recurring_token ) {
		$request_url  = $this->get_api_url_base() . 'customer-token/v1/tokens/' . $recurring_token . '/order';
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'body'       => $this->get_recurring_body( $order ),
		);

		KCO_WC()->logger->log( 'Create recurring order request (' . $request_url . ') ' . stripslashes_deep( json_encode( $request_args ) ) );
		krokedil_log_events( $order->get_id(), 'Create recurring order request', $request_args );
		$response = wp_safe_remote_post( $request_url, $request_args );

		KCO_WC()->logger->log( 'Create recurring order response' . stripslashes_deep( json_encode( $response ) ) );
		krokedil_log_events( $order->get_id(), 'Create recurring order response', $response );
		return $response;
	}

	public function get_recurring_body( $order ) {
		$order_id = $order->get_id();

		$order_lines = array();

		foreach ( $order->get_items() as $item ) {
			array_push( $order_lines, KCO_WC()->order_lines_from_order->get_order_line_items( $item ) );
		}
		foreach ( $order->get_fees() as $fee ) {
			array_push( $order_lines, KCO_WC()->order_lines_from_order->get_order_line_fees( $fee ) );
		}
		array_push( $order_lines, KCO_WC()->order_lines_from_order->get_order_line_shipping( $order ) );

		$body = array(
			'order_amount'      => KCO_WC()->order_lines_from_order->get_order_amount( $order_id ),
			'order_lines'       => $order_lines,
			'purchase_currency' => $order->get_currency(),
			'order_tax_amount'  => KCO_WC()->order_lines_from_order->get_total_tax( $order_id ),
		);
		return json_encode( $body );
	}
}
