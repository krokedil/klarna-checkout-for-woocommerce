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
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_shipping_method' ), 9999 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_klarna_order' ), 9999 );

		// Handle potential shipping selection errors.
		add_filter( 'woocommerce_shipping_chosen_method', array( __CLASS__, 'maybe_register_shipping_error' ), 9999, 3 );
		add_action( 'woocommerce_shipping_method_chosen', array( __CLASS__, 'maybe_throw_shipping_error' ), 9999 );
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

		$data = false;

		/*
		 * If - During the normal checkout flow.
		 * Else If - During the placing of the order.
		 */
		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore
			wp_parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore
			if ( isset( $post_data['kco_shipping_data'] ) ) {
				$data = $post_data['kco_shipping_data'];
			}
		} elseif ( isset( $_POST['kco_shipping_data'] ) ) { // phpcs:ignore
			$data = $_POST['kco_shipping_data']; // phpcs:ignore
		}

		// If we have data, update the shipping.
		if ( ! empty( $data ) ) {
			WC()->session->set( 'kco_shipping_data', $data );
			kco_update_wc_shipping( json_decode( $data, true ) );
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

	/**
	 * Maybe registers an error if we are attempting to set a new shipping method during the checkout process.
	 * WooCommerce will in some cases reset the shipping selection, instead of throwing an error if shipping options
	 * have changed. In our case its better to throw an error for the customer to see, so they can try again
	 * or select another shipping option.
	 *
	 * @param string $default The shipping method id that would be set as the default method.
	 * @param array  $rates The rates calculated when getting the default shipping method.
	 * @param string $chosen_method The shipping method id that was chosen by the customer.
	 *
	 * @return string
	 */
	public static function maybe_register_shipping_error( $default, $rates, $chosen_method ) {
		// Only do this if we are during the checkout process.
		if ( did_action( 'woocommerce_checkout_process' ) <= 0 ) {
			return $default;
		}

		// Only do this if KCO is the selected payment method and shipping in the iframe is selected.
		if ( 'kco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return $default;
		}

		$chosen_method = trim( $chosen_method );
		if ( empty( $chosen_method ) ) {
			return $default;
		}

		$options = get_option( 'woocommerce_kco_settings', array() );
		if ( 'yes' !== $options['shipping_methods_in_iframe'] ?? 'no' ) {
			return $default;
		}

		// The Klarna Shipping Service sets the chosen shipping method to the method without the instance ID, so if $chosen method === 'klarna_kss' return $default.
		if ( 'klarna_kss' === $chosen_method ) {
			return $default;
		}

		// This covers for situations where the shipping rate packages may be changed through a hook, which may result in an incorrect shipping method change assessment.
		if ( $default === $chosen_method ) {
			return $default;
		}

		KCO_Logger::log( "Checkout error - Shipping methods where changed during the checkout process by WooCommerce. Chosen shipping method by the customer was $chosen_method, WooCommerce wanted to set $default instead" );

		/*
		 * Add a filter to allow people to set if they want to automatically correct shipping discrepancies instead of throwing an error.
		 * Note however that this is not recommended. If you do this, and the shipping method that the customer selected is no longer available,
		 * then unexpected issues might happen. Only do this if you are sure the chosen method actually exists and is available.
		 */
		if ( apply_filters( 'kco_shipping_auto_correct', false, $default, $rates, $chosen_method ) ) {
			KCO_Logger::log( "Checkout error - Correcting the shipping method to the customers chosen method: $chosen_method" );
			return $chosen_method;
		}

		// If we are not auto-correcting the shipping method, we return the default, but trigger our action. This is so we can throw the error at a later time.
		do_action( 'kco_checkout_shipping_error' );
		return $default;
	}

	/**
	 * Actually throws the error registered previously.
	 * This is moved to happen on a separate action instead, since we need to allow WooCommerce to set a couple sessions.
	 * This prevents customers needing to reload the page.
	 *
	 * @return void
	 * @throws Exception Exception with the error message.
	 */
	public static function maybe_throw_shipping_error() {
		if ( did_action( 'kco_checkout_shipping_error' ) <= 0 ) {
			return;
		}

		KCO_Logger::log( 'Checkout error - Printing shipping error message to the customer.' );
		throw new Exception( __( 'The shipping methods have been changed during the checkout process. Please verify your selected shipping method and try again.', 'klarna-checkout-for-woocommerce' ) );
	}
} new KCO_Checkout();
