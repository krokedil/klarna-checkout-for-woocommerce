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
		$cart = $_POST['checkout']['cart'];

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
	 * Save order notes value to session and use it when creating the order.
	 */
	public static function kco_wc_update_order_notes() {
		$post = $_POST;

		if ( '' !== $_POST['order_notes'] ) {
			WC()->session->set( 'kco_wc_order_notes', wp_kses_post( $_POST['order_notes'] ) );
		}

		wp_die();
	}

	public static function kco_wc_refresh_checkout_fragment() {
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
