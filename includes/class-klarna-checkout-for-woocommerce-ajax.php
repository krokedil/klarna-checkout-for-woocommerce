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
			'kco_wc_update_cart' => true,
			'kco_wc_update_shipping' => true,
			'kco_wc_update_order_notes' => true,
			'kco_wc_refresh_checkout_fragment' => true,
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
			WC()->cart->set_quantity( $cart_key, $cart_value['qty'], false );
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_fees();
			WC()->cart->calculate_totals();
			KCO_WC()->api->request_pre_update_order();
		}

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
	public static function kco_wc_update_order_notes() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_update_order_notes' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$post = $_POST;

		if ( '' !== $_POST['order_notes'] ) {
			WC()->session->set( 'kco_wc_order_notes', wp_kses_post( $_POST['order_notes'] ) );
		}

		wp_die();
	}

	/**
	 * Refresh checkout fragment.
	 */
	public static function kco_wc_refresh_checkout_fragment() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'kco_wc_refresh_checkout_fragment' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( 'false' === $_POST['kco'] ) {
			// Set chosen payment method to first gateway that is not Klarna Checkout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'klarna_checkout_for_woocommerce' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'klarna_checkout_for_woocommerce' );
		}

		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		ob_start();
		wc_get_template( 'checkout/form-checkout.php', array(
			'checkout' => WC()->checkout(),
		) );
		$checkout_output = ob_get_clean();

		$data = array(
			'fragments' => array(
				'checkout' => $checkout_output,
			),
		);

		wp_send_json_success( $data );
		wp_die();
	}

}
Klarna_Checkout_For_WooCommerce_AJAX::init();
