<?php
/**
 * AJAX class file.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_AJAX class.
 *
 * Registers AJAX actions for Klarna Checkout for WooCommerce.
 *
 * @extends WC_AJAX
 */
class KCO_AJAX extends WC_AJAX {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'kco_wc_update_cart'                    => true,
			'kco_wc_update_shipping'                => true,
			'kco_wc_change_payment_method'          => true,
			'kco_wc_iframe_shipping_address_change' => true,
			'kco_wc_set_session_value'              => true,
			'kco_wc_get_klarna_order'               => true,
			'kco_wc_log_js'                         => true,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Cart quantity update function.
	 */
	public static function kco_wc_update_cart() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'kco_wc_update_cart' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

		$values = array();
		if ( isset( $_POST['checkout'] ) ) {
			wp_parse_str( wp_unslash( $_POST['checkout'] ), $values );// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		$cart = $values['cart'];

		if ( ! is_iterable( $cart ) ) {
			wp_send_json_error();
		}

		foreach ( $cart as $cart_key => $cart_value ) {
			$new_quantity = (int) $cart_value['qty'];
			WC()->cart->set_quantity( $cart_key, $new_quantity, false );
		}
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
		$klarna_order    = KCO_WC()->api->update_klarna_order( $klarna_order_id );

		// If the update failed return error.
		if ( is_wp_error( $klarna_order ) ) {
			wp_send_json_error();
		}
		wp_die();
	}

	/**
	 * Refresh checkout fragment.
	 */
	public static function kco_wc_change_payment_method() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'kco_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$switch_to_klarna   = isset( $_POST['kco'] ) ? sanitize_text_field( wp_unslash( $_POST['kco'] ) ) : '';

		if ( 'false' === $switch_to_klarna ) {
			// Set chosen payment method to first gateway that is not Klarna Checkout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'kco' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'kco' );
		}

		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);

		wp_send_json_success( $data );
	}

	/**
	 * Iframe change callback function.
	 */
	public static function kco_wc_iframe_shipping_address_change() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'kco_wc_iframe_shipping_address_change' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

		// Check if we have a klarna order id.
		if ( empty( $klarna_order_id ) ) {
			wc_add_notice( 'Klarna order id is missing.', 'error' );
			wp_send_json_error();
		}

		if ( isset( $_REQUEST['data'] ) && is_array( $_REQUEST['data'] ) ) {
			$address = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['data'] ) );
		}

		$customer_data = array();

		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );

		if ( isset( $address['email'] ) ) {
			$customer_data['billing_email'] = $address['email'];
		}

		if ( isset( $address['postal_code'] ) ) {
			$customer_data['billing_postcode']  = $address['postal_code'];
			$customer_data['shipping_postcode'] = $address['postal_code'];
		}

		if ( isset( $address['given_name'] ) ) {
			$customer_data['billing_first_name']  = $address['given_name'];
			$customer_data['shipping_first_name'] = $address['given_name'];
		}

		if ( isset( $address['family_name'] ) ) {
			$customer_data['billing_last_name']  = $address['family_name'];
			$customer_data['shipping_last_name'] = $address['family_name'];
		}

		if ( isset( $klarna_order['billing_address']['region'] ) ) {
			$customer_data['billing_state']  = $klarna_order['billing_address']['region'];
			$customer_data['shipping_state'] = $klarna_order['shipping_address']['region'];
		}

		if ( isset( $address['country'] ) && kco_wc_country_code_converter( $address['country'] ) ) {
			$country                           = kco_wc_country_code_converter( $address['country'] );
			$customer_data['billing_country']  = $country;
			$customer_data['shipping_country'] = $country;
		}

		WC()->customer->set_props( $customer_data );
		WC()->customer->save();

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id );

		if ( is_wp_error( $klarna_order ) ) {
			wp_send_json_error();
		}

		// If the Klarna order was false, there was no update needed. Don't change any address data.
		if ( ! $klarna_order ) {
			wp_send_json_success( false );
		}

		wp_send_json_success(
			array(
				'billing_address'  => isset( $klarna_order['billing_address'] ) ? $klarna_order['billing_address'] : array(),
				'shipping_address' => isset( $klarna_order['shipping_address'] ) ? $klarna_order['shipping_address'] : array(),
			)
		);
	}

	/**
	 * Gets the klarna order from session.
	 *
	 * @return void
	 */
	public static function kco_wc_get_klarna_order() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'kco_wc_get_klarna_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$klarna_order = KCO_WC()->api->get_klarna_order( WC()->session->get( 'kco_wc_order_id' ) );
		if ( ! $klarna_order ) {
			wp_send_json_error( $klarna_order );
		}

		// Convert the billing region to unicode format.
		if ( isset( $klarna_order['billing_address']['region'] ) ) {
			$region                                    = $klarna_order['billing_address']['region'];
			$country                                   = $klarna_order['billing_address']['country'];
			$klarna_order['billing_address']['region'] = kco_convert_region( $region, $country );
		}

		// Convert the shipping region to unicode format.
		if ( isset( $klarna_order['shipping_address']['region'] ) ) {
			$region                                     = $klarna_order['shipping_address']['region'];
			$country                                    = $klarna_order['shipping_address']['country'];
			$klarna_order['shipping_address']['region'] = kco_convert_region( $region, $country );
		}

		wp_send_json_success(
			array(
				'billing_address'  => $klarna_order['billing_address'],
				'shipping_address' => $klarna_order['shipping_address'],
			)
		);

	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function kco_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'kco_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$posted_message  = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
		$message         = "Frontend JS $klarna_order_id: $posted_message";
		KCO_Logger::log( $message );
		wp_send_json_success();
	}
}
KCO_AJAX::init();
