<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for managing actions during the checkout process.
 */
class KCO_Checkout {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_shipping_data_input' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_shipping_method' ), 1 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_klarna_order' ), 9999 );
	}

	/**
	 * Add a hidden input field for the shipping data from Klarna.
	 *
	 * @param array $fields The WooCommerce checkout fields.
	 * @return array
	 */
	public function add_shipping_data_input( $fields ) {
		$default = '';

		if ( is_checkout() ) {
			$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
			$shipping_data   = get_transient( 'kss_data_' . $klarna_order_id );
			$default         = wp_json_encode( $shipping_data );
		}

		$fields['billing']['kco_shipping_data'] = array(
			'type'    => 'hidden',
			'class'   => array( 'kco_shipping_data' ),
			'default' => $default,
		);

		return $fields;
	}

	/**
	 * Update the shipping method in WooCommerce based on what Klarna has sent us.
	 *
	 * @return void
	 */
	public function update_shipping_method() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'kco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore
			parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore
			if ( isset( $post_data['kco_shipping_data'] ) ) {
				WC()->session->set( 'kco_shipping_data', $post_data['kco_shipping_data'] );
				$data = json_decode( $post_data['kco_shipping_data'], true );
				kco_update_wc_shipping( $data );
			}
		}
	}

	/**
	 * Update the Klarna order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_klarna_order() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'kco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}
		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

		if ( empty( $klarna_order_id ) ) {
			KCO_Logger::log( 'Missing WC session kco_wc_order_id during update Klarna order sequence.' );
			return;
		}

		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );

		if ( $klarna_order && 'checkout_incomplete' === $klarna_order['status'] ) {
			// If it is, update order.
			$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id );
		}

		// If cart doesn't need payment anymore - reload the checkout page.
		if ( apply_filters( 'kco_check_if_needs_payment', true ) ) {
			if ( ! WC()->cart->needs_payment() && 'checkout_incomplete' === $klarna_order['status'] ) {
				WC()->session->reload_checkout = true;
			}
		}
	}
} new KCO_Checkout();
