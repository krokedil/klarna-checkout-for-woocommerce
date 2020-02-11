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
			'kco_wc_update_shipping'                => true,
			'kco_wc_change_payment_method'          => true,
			'kco_wc_update_klarna_order'            => true,
			'kco_wc_iframe_shipping_address_change' => true,
			'kco_wc_set_session_value'              => true,
			'kco_wc_get_klarna_order'               => true,
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
	 * Update shipping method function.
	 */
	public static function kco_wc_update_shipping() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_update_shipping' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

		if ( isset( $_POST['data'] ) && is_array( $_POST['data'] ) ) {
			$shipping_option           = $_POST['data'];
			$chosen_shipping_methods   = array();
			$chosen_shipping_methods[] = wc_clean( $shipping_option['id'] );
			WC()->session->set( 'chosen_shipping_methods', apply_filters( 'kco_wc_chosen_shipping_method', $chosen_shipping_methods ) );
		}
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

		$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id );

		// If the update failed - reload the checkout page and display the error.
		if ( ! $klarna_order ) {
			wp_send_json_error();
			wp_die();
		}
		$shipping_option_name = 'shipping_method_0_' . str_replace( ':', '', $shipping_option['id'] );
		$data                 = array(
			'shipping_option_name' => $shipping_option_name,
		);
		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Refresh checkout fragment.
	 */
	public static function kco_wc_change_payment_method() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( 'false' === $_POST['kco'] ) {
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
		wp_die();
	}

	/**
	 * Updates Klarna order.
	 */
	public static function kco_wc_update_klarna_order() {

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {

			$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

			// Set empty return array for errors.
			$return = array();

			// Check if we have a klarna order id.
			if ( empty( $klarna_order_id ) ) {
				wc_add_notice( 'Klarna order id is missing.', 'error' );
				wp_send_json_error();
				wp_die();
			} else {
				// Get the Klarna order from Klarna.
				$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );

				// Check if we got a wp_error.
				if ( ! $klarna_order ) {
					wp_send_json_error();
					wp_die();
				}

				// Get the Klarna order object.
				// Calculate cart totals.
				WC()->cart->calculate_fees();
				WC()->cart->calculate_totals();

				// Check if order needs payment.
				if ( apply_filters( 'kco_check_if_needs_payment', true ) ) {
					if ( ! WC()->cart->needs_payment() && 'checkout_incomplete' === $klarna_order['status'] ) {
						$return['redirect_url'] = wc_get_checkout_url();
						wp_send_json_error( $return );
						wp_die();
					}
				}

				// Check if payment status is checkout_incomplete.
				if ( 'checkout_incomplete' === $klarna_order['status'] ) {
					// If it is, update order.
					$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id );

					// If the update failed - reload the checkout page and display the error.
					if ( ! $klarna_order ) {
						wp_send_json_error();
						wp_die();
					}
				} elseif ( 'checkout_complete' !== $klarna_order['status'] ) {
					// Checkout is not completed or incomplete. Send to cart and display error.
					$return['redirect_url'] = add_query_arg( 'kco-order', 'error', wc_get_cart_url() );
					wp_send_json_error( $return );
					wp_die();
				}
			}
		}

		// Everything is okay if we get here. Send empty success and kill wp.
		wp_send_json_success();
		wp_die();
	}

	/**
	 * Iframe change callback function.
	 */
	public static function kco_wc_iframe_shipping_address_change() {

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

		// Check if we have a klarna order id.
		if ( empty( $klarna_order_id ) ) {
			wc_add_notice( 'Klarna order id is missing.', 'error' );
			wp_send_json_error();
			wp_die();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_iframe_shipping_address_change' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		if ( isset( $_REQUEST['data'] ) && is_array( $_REQUEST['data'] ) ) {
			$address = array_map( 'sanitize_text_field', $_REQUEST['data'] );
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

		if ( ! $klarna_order ) {
			wp_send_json_error();
			wp_die();
		}

		wp_send_json_success(
			array(
				'billing_address'  => $klarna_order['billing_address'],
				'shipping_address' => $klarna_order['shipping_address'],
			)
		);
		wp_die();
	}

	/**
	 * Gets the klarna order from session.
	 *
	 * @return void
	 */
	public static function kco_wc_get_klarna_order() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_get_klarna_order' ) ) { // Input var okay.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$klarna_order = KCO_WC()->api->get_klarna_order( WC()->session->get( 'kco_wc_order_id' ) );
		if ( ! $klarna_order ) {
			wp_send_json_error( $klarna_order );
			wp_die();
		}
		wp_send_json_success(
			array(
				'billing_address'  => $klarna_order['billing_address'],
				'shipping_address' => $klarna_order['shipping_address'],
			)
		);
		wp_die();

	}
}

KCO_AJAX::init();
