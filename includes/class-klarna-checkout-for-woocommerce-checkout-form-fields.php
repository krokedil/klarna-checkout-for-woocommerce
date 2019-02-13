<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields {

	public static $klarna_order;

	public function __construct() {
		add_action( 'kco_wc_after_snippet', array( $this, 'add_woocommerce_checkout_form_fields' ) );
	}

	public function add_woocommerce_checkout_form_fields() {
		echo '<div id="kco-hidden" style="position:absolute;top:-9999px;left:-9999px;">';
		// Billing fields.
		$billing_fields = WC()->checkout()->get_checkout_fields( 'billing' );
		foreach ( $billing_fields as $key => $field ) {
			if ( in_array( $key, array( 'billing_country', 'billing_email', 'billing_state', 'billing_postcode' ) ) ) {
				if ( isset( $field['country_field'], $fields[ $field['country_field'] ] ) ) {
					$field['country'] = WC()->checkout->get_value( $field['country_field'] );
				}
				woocommerce_form_field( $key, $field, WC()->checkout->get_value( $key ) );
			}
		}

		// Shipping fields.
		$shipping_fields = WC()->checkout()->get_checkout_fields( 'shipping' );
		foreach ( $shipping_fields as $key => $field ) {
			if ( in_array( $key, array( 'shipping_country', 'shipping_state', 'shipping_postcode' ) ) ) {
				if ( isset( $field['country_field'], $fields[ $field['country_field'] ] ) ) {
					$field['country'] = WC()->checkout->get_value( $field['country_field'] );
				}
				woocommerce_form_field( $key, $field, WC()->checkout->get_value( $key ) );
			}
		}
		echo '</div>';
	}


	public static function maybe_set_klarna_order() {
		if ( empty( self::$klarna_order ) ) {
			if ( empty( WC()->session->get( 'kco_wc_order_id' ) ) ) {
				self::$klarna_order = null;
			} else {
				self::$klarna_order = KCO_WC()->api->request_pre_get_order( WC()->session->get( 'kco_wc_order_id', true ) );
			}
		}
		return self::$klarna_order;
	}

	public static function maybe_set_customer_email() {
		$klarna_order = self::maybe_set_klarna_order();
		// Check that we got a response.
		if ( empty( $klarna_order ) || is_wp_error( $klarna_order ) ) {
			$email = null;
		} else {
			$klarna_order = json_decode( $klarna_order['body'] );
			$email        = $klarna_order->billing_address->email;
			WC()->customer->set_billing_email( $email );
		}
		return $email;
	}

	public static function maybe_set_customer_state() {
		$billing_state  = '';
		$shipping_state = '';
		if ( 'US' === WC()->customer->get_billing_country() ) {
			$klarna_order = self::maybe_set_klarna_order();
			// Check that we got a response.
			if ( empty( $klarna_order ) || is_wp_error( $klarna_order ) ) {
				$billing_state  = null;
				$shipping_state = null;
			} else {
				$klarna_order   = json_decode( $klarna_order['body'] );
				$billing_state  = $klarna_order->billing_address->region;
				$shipping_state = $klarna_order->shipping_address->region;
				WC()->customer->set_billing_state( $billing_state );
				WC()->customer->set_shipping_state( $shipping_state );
			}
		}
		return array(
			'billing_state'  => $billing_state,
			'shipping_state' => $shipping_state,
		);
	}
} new Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields();
