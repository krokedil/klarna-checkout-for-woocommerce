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
		$log_array = array(
			'headers'    => $request_args['headers'],
			'user-agent' => $request_args['user-agent'],
			'body'       => json_decode( $request_args['body'] ),
		);
		KCO_WC()->logger->log( 'Create Klarna order (' . $request_url . ') ' . json_encode( $request_args ) );
		krokedil_log_events( null, 'Pre Create Order request args', $log_array );
		$response = wp_safe_remote_post( $request_url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $this->extract_error_messages( $response );
		}

		if ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) {
			$klarna_order = json_decode( $response['body'] );
			$this->save_order_id_to_session( sanitize_key( $klarna_order->order_id ) );
			$this->save_order_api_to_session( $klarna_order );
			$log_order = clone $klarna_order;
			$log_order->html_snippet = '';
			krokedil_log_events( null, 'Pre Create Order response', $log_order );
			return $klarna_order;
		} else if( $response['response']['code'] === 405 ) {
			$error = $response['response']['message'];
			return $error;
		} else {
			$error = $this->extract_error_messages( $response );
			krokedil_log_events( null, 'Pre Create Order response', $error );
			return $error;
		}
	}

	/**
	 * Retrieve ongoing Klarna order.
	 *
	 * @param  string $klarna_order_id Klarna order ID.
	 *
	 * @return object $klarna_order    Klarna order.
	 */
	public function request_pre_retrieve_order( $klarna_order_id ) {
		$request_url  = $this->get_api_url_base() . 'checkout/v3/orders/' . $klarna_order_id;
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
		);
		krokedil_log_events( null, 'Pre Retrieve Order request args', $request_args );
		KCO_WC()->logger->log( 'Retrieve ongoing Klarna order (' . $request_url . ') ' . json_encode( $request_args ) );

		$response = wp_safe_remote_get( $request_url, $request_args );

		if ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) {
			$klarna_order = json_decode( $response['body'] );
			$log_order = clone $klarna_order;
			$log_order->html_snippet = '';
			krokedil_log_events( null, 'Pre Retrieve Order response', $log_order );
			return $klarna_order;
		} else {
			$error = $this->extract_error_messages( $response );
			krokedil_log_events( null, 'Pre Retrieve Order response', $error );
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
		$log_array = array(
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

		if ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) {
			WC()->session->set( 'kco_wc_update_md5', md5( serialize( $request_args ) ) );

			$klarna_order = json_decode( $response['body'] );
			$log_order = clone $klarna_order;
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
	 *
	 * @return WP_Error|array $response
	 */
	public function request_post_get_order( $klarna_order_id ) {
		$request_url  = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id;
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
		);
		$response = wp_safe_remote_get( $request_url, $request_args );
		krokedil_log_events( null, 'Post Get Order response', stripslashes_deep( $response ) );
		KCO_WC()->logger->log( 'Post Get Order response (' . $request_url . ') ' . stripslashes_deep( json_encode( $response ) ) );

		return $response;
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
	 * @param  array $merchant_references Array of merchant references.
	 *
	 * @return WP_Error|array $response
	 */
	public function request_post_set_merchant_reference( $klarna_order_id, $merchant_references ) {
		$request_url  = $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $klarna_order_id . '/merchant-references';
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => 'PATCH',
			'body'       => wp_json_encode( array(
				'merchant_reference1' => $merchant_references['merchant_reference1'],
				'merchant_reference2' => $merchant_references['merchant_reference2'],
			) ),
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
			$order = $this->request_pre_retrieve_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				$order = $this->request_pre_create_order();
			} elseif ( 'checkout_incomplete' === $order->status ) {
				// Only update order if its status is incomplete.
				$this->request_pre_update_order();
			}
		} else {
			if ( is_order_received_page() ) {
				return;
			}

			$order = $this->request_pre_create_order();
		}

		return $order;
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
			$locale = explode('_', get_locale());
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
				} elseif( 'sv' == $locale ) {
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
				if ( 'nb' == $locale || 'nn' == $locale ) {
					$klarna_locale = 'nb-no';
				} else {
					$klarna_locale = 'en-no';
				}
				break;
			case 'SE':
				if ( 'sv' == $locale ) {
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

		$request_args = array(
			'purchase_country'   => $this->get_purchase_country(),
			'purchase_currency'  => $this->get_purchase_currency(),
			'locale'             => $this->get_purchase_locale(),
			'merchant_urls'      => $this->get_merchant_urls(),
			'order_amount'       => KCO_WC()->order_lines->get_order_amount(),
			'order_tax_amount'   => KCO_WC()->order_lines->get_order_tax_amount(),
			'order_lines'        => KCO_WC()->order_lines->get_order_lines(),
			'shipping_countries' => $this->get_shipping_countries(),
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
		$request_args['options']                                             = array();
		$request_args['options']['allow_separate_shipping_address']          = $this->get_allow_separate_shipping_address();
		$request_args['options']['date_of_birth_mandatory']                  = $this->get_dob_mandatory();
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
		if ( 'create' === $request_type ) {
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
					$request_args['customer']['type']                  = $customer_type;
				}
			}

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
			$color_settings['color_button'] = $this->check_option_field( 'color_button' );
		}

		if ( $this->check_option_field( 'color_button_text' ) ) {
			$color_settings['color_button_text'] = $this->check_option_field( 'color_button_text' );
		}

		if ( $this->check_option_field( 'color_checkbox' ) ) {
			$color_settings['color_checkbox'] = $this->check_option_field( 'color_checkbox' );
		}

		if ( $this->check_option_field( 'color_checkbox_checkmark' ) ) {
			$color_settings['color_checkbox_checkmark'] = $this->check_option_field( 'color_checkbox_checkmark' );
		}

		if ( $this->check_option_field( 'color_header' ) ) {
			$color_settings['color_header'] = $this->check_option_field( 'color_header' );
		}

		if ( $this->check_option_field( 'color_link' ) ) {
			$color_settings['color_link'] = $this->check_option_field( 'color_link' );
		}

		if ( $this->check_option_field( 'radius_border' ) ) {
			$color_settings['radius_border'] = $this->check_option_field( 'radius_border' );
		}

		if ( count( $color_settings ) > 0 ) {
			return $color_settings;
		}

		return false;
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
	private function extract_error_messages( $response ) {
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

}
