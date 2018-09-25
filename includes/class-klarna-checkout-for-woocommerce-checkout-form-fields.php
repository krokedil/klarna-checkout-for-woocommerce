<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields {

	public static $klarna_order;

	public function __construct() {
		add_action( 'kco_wc_after_snippet', array( $this, 'add_email_field_to_form' ) );
		add_action( 'kco_wc_after_snippet', array( $this, 'add_state_fields_to_form' ) );
	}

	public function add_email_field_to_form() {
		echo '<input type="email" style="display:none" class="input-text " name="billing_email" id="billing_email" placeholder="">';
	}
	public function add_state_fields_to_form() {
		echo '<input type="text" style="display:none" class="input-text " name="billing_state" id="billing_state" placeholder="">';
		echo '<input type="text" style="display:none" class="input-text " name="shipping_state" id="shipping_state" placeholder="">';
	}

	public static function maybe_set_klarna_order() {
		if ( empty( self::$klarna_order ) ) {
			self::$klarna_order = KCO_WC()->api->request_pre_get_order( WC()->session->get( 'kco_wc_order_id', true ) );
		}
		return self::$klarna_order;
	}

	public static function maybe_set_customer_email() {
		$klarna_order = self::maybe_set_klarna_order();
		$klarna_order = json_decode( $klarna_order['body'] );
		$email        = $klarna_order->billing_address->email;
		WC()->customer->set_billing_email( $email );

		return $email;
	}

	public static function maybe_set_customer_state() {
		$billing_state  = '';
		$shipping_state = '';
		if ( 'US' === WC()->customer->get_billing_country() ) {
			$klarna_order   = self::maybe_set_klarna_order();
			$klarna_order   = json_decode( $klarna_order['body'] );
			$billing_state  = $klarna_order->billing_address->region;
			$shipping_state = $klarna_order->shipping_address->region;
			WC()->customer->set_billing_state( $billing_state );
			WC()->customer->set_shipping_state( $shipping_state );
		}
		return array(
			'billing_state'  => $billing_state,
			'shipping_state' => $shipping_state,
		);
	}
} new Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields();
