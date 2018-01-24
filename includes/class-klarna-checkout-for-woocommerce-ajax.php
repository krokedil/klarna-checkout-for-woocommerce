<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_AJAX class.
 *
 * Registers AJAX actions for Klarna Checkout for WooCommerce.
 *
 * @extends WC_AJAX
 */
class Klarna_Checkout_For_WooCommerce_AJAX extends WC_AJAX {

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
			'kco_wc_update_extra_fields'            => true,
			'kco_wc_change_payment_method'          => true,
			'kco_wc_update_klarna_order'            => true,
			'kco_wc_iframe_shipping_address_change' => true,
			'kco_wc_checkout_error'                 => true,
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
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_update_cart' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$values = array();
		parse_str( $_POST['checkout'], $values );
		$cart = $values['cart'];

		foreach ( $cart as $cart_key => $cart_value ) {
			$new_quantity = (int) $cart_value['qty'];
			WC()->cart->set_quantity( $cart_key, $new_quantity, false );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();
		KCO_WC()->api->request_pre_update_order();

		wp_die();
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

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( isset( $_POST['shipping'] ) && is_array( $_POST['shipping'] ) ) {
			foreach ( $_POST['shipping'] as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();
		KCO_WC()->api->request_pre_update_order();

		wp_die();
	}

	/**
	 * Save order notes value to session and use it when creating the order.
	 */
	public static function kco_wc_update_extra_fields() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_update_extra_fields' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		if ( is_array( $_POST['extra_fields_values'] ) ) {
			$values = array_map( 'sanitize_textarea_field', $_POST['extra_fields_values'] );
			WC()->session->set( 'kco_wc_extra_fields_values', $values );
		}

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
		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
			$klarna_order_id = KCO_WC()->api->get_order_id_from_session();
			$klarna_order    = KCO_WC()->api->request_pre_retrieve_order( $klarna_order_id );

			if ( 'checkout_incomplete' === $klarna_order->status ) {
				WC()->cart->calculate_shipping();
				WC()->cart->calculate_fees();
				WC()->cart->calculate_totals();
				KCO_WC()->api->request_pre_update_order();
			}
		}

		wp_send_json_success();
		wp_die();
	}

	/**
	 * Iframe change callback function.
	 */
	public static function kco_wc_iframe_shipping_address_change() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_iframe_shipping_address_change' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		if ( isset( $_REQUEST['data'] ) && is_array( $_REQUEST['data'] ) ) {
			$address = array_map( 'sanitize_text_field', $_REQUEST['data'] );
		}

		$customer_data = array();

		if ( isset( $address['email'] ) ) {
			$customer_data['email']         = $address['email'];
			$customer_data['billing_email'] = $address['email'];
		}

		if ( isset( $address['postal_code'] ) ) {
			$customer_data['postcode']          = $address['postal_code'];
			$customer_data['billing_postcode']  = $address['postal_code'];
			$customer_data['shipping_postcode'] = $address['postal_code'];
		}

		if ( isset( $address['given_name'] ) ) {
			$customer_data['first_name']          = $address['given_name'];
			$customer_data['billing_first_name']  = $address['given_name'];
			$customer_data['shipping_first_name'] = $address['given_name'];
		}

		if ( isset( $address['family_name'] ) ) {
			$customer_data['last_name']          = $address['family_name'];
			$customer_data['billing_last_name']  = $address['family_name'];
			$customer_data['shipping_last_name'] = $address['family_name'];
		}

		if ( isset( $address['region'] ) ) {
			$customer_data['state']          = $address['region'];
			$customer_data['billing_state']  = $address['region'];
			$customer_data['shipping_state'] = $address['region'];
		}

		if ( isset( $address['country'] ) && kco_wc_country_code_converter( $address['country'] ) ) {
			$country                           = kco_wc_country_code_converter( $address['country'] );
			$customer_data['country']          = $country;
			$customer_data['billing_country']  = $country;
			$customer_data['shipping_country'] = $country;
		}

		WC()->customer->set_props( $customer_data );
		WC()->customer->save();

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		KCO_WC()->api->request_pre_update_order();

		ob_start();
		woocommerce_order_review();
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
		wp_die();
	}

	/**
	 * Handles WooCommerce checkout error, after Klarna order has already been created.
	 */
	public static function kco_wc_checkout_error() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_checkout_error' ) ) { // Input var okay.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$redirect_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
		$redirect_url = add_query_arg( 'kco_wc', 'true', $redirect_url );

		wp_send_json_success( array( 'redirect' => $redirect_url ) );
		wp_die();
	}

}

Klarna_Checkout_For_WooCommerce_AJAX::init();
