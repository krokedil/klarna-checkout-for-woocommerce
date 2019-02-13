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
			'kco_wc_save_form_data'                 => true,
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

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

		$values = array();
		parse_str( $_POST['checkout'], $values );
		$cart = $values['cart'];

		foreach ( $cart as $cart_key => $cart_value ) {
			$new_quantity = (int) $cart_value['qty'];
			WC()->cart->set_quantity( $cart_key, $new_quantity, false );
		}

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
			$update_values  = array_map( 'sanitize_textarea_field', $_POST['extra_fields_values'] );
			$session_values = WC()->session->get( 'kco_wc_extra_fields_values', array() );

			// Update session array, instead of overwriting it.
			foreach ( $update_values as $update_key => $update_value ) {
				$session_values[ $update_key ] = $update_value;
			}

			WC()->session->set( 'kco_wc_extra_fields_values', $session_values );
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

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
			$klarna_order_id = KCO_WC()->api->get_order_id_from_session();

			// Set empty return array for errors.
			$return = array();

			// Check if we have a klarna order id.
			if ( empty( $klarna_order_id ) ) {
				// $klarna_order_id is missing, redirect the customer to cart page (to avoid infinite reloading of checkout page).
				KCO_WC()->logger->log( 'ERROR. Klarna Checkout order ID missing in kco_wc_update_klarna_order function. Redirecting customer to cart page.' );
				krokedil_log_events( null, 'ERROR. Klarna Checkout order ID missing in kco_wc_update_klarna_order function. Redirecting customer to cart page.', '' );
				$return['redirect_url'] = add_query_arg( 'kco-order', 'missing-id', wc_get_cart_url() );
				wp_send_json_error( $return );
				wp_die();
			} else {
				// Get the Klarna order from Klarna.
				$response = KCO_WC()->api->request_pre_get_order( $klarna_order_id );

				// Check if we got a wp_error.
				if ( is_wp_error( $response ) ) {
					// If wp_error, redirect with error.
					$return['redirect_url'] = add_query_arg( 'kco-order', 'error', wc_get_cart_url() );
					wp_send_json_error( $return );
					wp_die();
				}

				// Get the Klarna order object.
				$klarna_order = json_decode( $response['body'] );

				// Calculate cart totals
				WC()->cart->calculate_fees();
				WC()->cart->calculate_totals();

				// Check if order needs payment.
				if ( ! WC()->cart->needs_payment() && 'checkout_incomplete' === $klarna_order->status ) {
					$return['redirect_url'] = wc_get_checkout_url();
					wp_send_json_error( $return );
					wp_die();
				}

				// Check if payment status is checkout_incomplete.
				if ( 'checkout_incomplete' === $klarna_order->status ) {
					// If it is, update order.
					$response = KCO_WC()->api->request_pre_update_order();
					// If the update failed - reload the checkout page and display the error.
					if ( is_wp_error( $response ) ) {
						$url                    = add_query_arg(
							array(
								'kco-order' => 'error',
								'reason'    => base64_encode( $response->get_error_message() ),
							),
							wc_get_cart_url()
						);
						$return['redirect_url'] = $url;

						wp_send_json_error( $return );
						wp_die();
					}
				} elseif ( 'checkout_complete' !== $klarna_order->status ) {
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

		$klarna_order_id = KCO_WC()->api->get_order_id_from_session();

		// Check if we have a klarna order id.
		if ( empty( $klarna_order_id ) ) {
			// $klarna_order_id is missing, redirect the customer to cart page (to avoid infinite reloading of checkout page).
			KCO_WC()->logger->log( 'ERROR. Klarna Checkout order ID missing in kco_wc_iframe_shipping_address_change function. Redirecting customer to cart page.' );
			krokedil_log_events( null, 'ERROR. Klarna Checkout order ID missing in kco_wc_iframe_shipping_address_change function. Redirecting customer to cart page.', '' );
			$return                 = array();
			$return['redirect_url'] = add_query_arg( 'kco-order', 'missing-id', wc_get_cart_url() );
			wp_send_json_error( $return );
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

		// Send customer data to frontend.
		$email  = Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields::maybe_set_customer_email();
		$states = Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields::maybe_set_customer_state();

		if ( isset( $email ) ) {
			$customer_data['billing_email'] = $email;
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

		if ( isset( $states['billing_state'] ) ) {
			$customer_data['billing_state']  = $states['billing_state'];
			$customer_data['shipping_state'] = $states['shipping_state'];
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

		KCO_WC()->api->request_pre_update_order();

		/*
		ob_start();
		woocommerce_order_review();
		$html = ob_get_clean();*/

		wp_send_json_success(
			array(
				// 'html'   => $html,
				'customer_data' => $customer_data,
			)
		);
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
		if ( ! empty( $_POST['error_message'] ) ) { // Input var okay.
			$error_message = 'Error message: ' . sanitize_text_field( trim( $_POST['error_message'] ) );
		} else {
			$error_message = 'Error message could not be retreived';
		}

		KCO_WC()->logger->log( 'Checkout form submission failed. ' . $error_message );
		krokedil_log_events( null, 'Checkout form submission failed', $error_message );

		if ( ! empty( $_GET['kco_wc_order_id'] ) ) { // Input var okay.
			$klarna_order_id = $_GET['kco_wc_order_id'];
		} else {
			$klarna_order_id = KCO_WC()->api->get_order_id_from_session();
		}

		// Check if we have items in cart. If not return redirect URL.
		if ( WC()->cart->is_empty() && ! is_customize_preview() && apply_filters( 'woocommerce_checkout_redirect_empty_cart', true ) ) {
			wp_send_json_success( array( 'redirect' => wc_get_page_permalink( 'cart' ) ) );
			wp_die();
		}

		// Check if Woo order with Klarna order ID already exist.
		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_wc_klarna_order_id',
			'meta_value'  => $klarna_order_id,
		);
		$orders     = get_posts( $query_args );

		if ( empty( $orders ) ) {
			// No order found. Create order via fallback sequence.
			KCO_WC()->logger->log( 'Starting Fallback order creation.' );
			krokedil_log_events( null, 'Starting Fallback order creation.', '' );
			$order = Klarna_Checkout_For_WooCommerce_Create_Local_Order_Fallback::create( $klarna_order_id, $error_message );

			if ( is_object( $order ) ) {
				KCO_WC()->logger->log( 'Fallback order creation done. Redirecting customer to thank you page.' );
				krokedil_log_events( $order->get_id(), 'Fallback order creation done. Redirecting customer to thank you page.', '' );
				$note = sprintf( __( 'This order was made as a fallback due to an error in the checkout (%s). Please verify the order with Klarna.', 'klarna-checkout-for-woocommerce' ), $error_message );
				$order->add_order_note( $note );
				$order->update_status( 'on-hold' );
				$redirect_url = $order->get_checkout_order_received_url();
			} else {
				KCO_WC()->logger->log( 'Fallback order creation ERROR. Redirecting customer to simplified thank you page.' . json_decode( $order ) );
				krokedil_log_events( null, 'Fallback order creation ERROR. Redirecting customer to simplified thank you page.', $order );
				$redirect_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
				$redirect_url = add_query_arg( 'kco_checkout_error', 'true', $redirect_url );
			}

			wp_send_json_success( array( 'redirect' => $redirect_url ) );
			wp_die();
		} else {
			// Order already exist in Woo. Redirect customer to the corresponding order received page.
			$order_id     = $orders[0];
			$order        = wc_get_order( $order_id );
			$redirect_url = $order->get_checkout_order_received_url();
			KCO_WC()->logger->log( 'Order already exist in Woo. Redirecting customer to thank you page. ' . json_decode( $redirect_url ) );
			krokedil_log_events( $order_id, 'Order already exist in Woo. Redirecting customer to thank you page. ', $redirect_url );
			wp_send_json_success( array( 'redirect' => $redirect_url ) );
			wp_die();
		}
	}

	/**
	 * Handles the saving of form data to session.
	 */
	public static function kco_wc_save_form_data() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_save_form_data' ) ) { // Input var okay.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		if ( ! empty( $_POST['form'] ) ) {
			$form = $_POST['form'];
			WC()->session->set( 'kco_checkout_form', $form );
		}
		wp_send_json_success();
		wp_die();
	}
}

Klarna_Checkout_For_WooCommerce_AJAX::init();
