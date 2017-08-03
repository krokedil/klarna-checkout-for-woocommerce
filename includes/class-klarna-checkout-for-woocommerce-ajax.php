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
			'kco_wc_update_quantity' => true,
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
	 * Cart quantity update function
	 */
	public function kco_wc_update_quantity() {
		$cart = $_POST['cart'];

		foreach ( $cart as $cart_key => $cart_value ) {
			WC()->cart->set_quantity( $cart_key, $cart_value['qty'], false );
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_fees();
			WC()->cart->calculate_totals();
			KCO_WC()->api->request_pre_update_order();
		}

		exit;
	}
	
}

Klarna_Checkout_For_WooCommerce_AJAX::init();
