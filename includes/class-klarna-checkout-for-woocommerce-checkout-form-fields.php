<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields {

	public function __construct() {
		add_action( 'kco_wc_after_snippet', array( $this, 'add_email_field_to_form' ) );
	}

	public function add_email_field_to_form() {
		echo '<input type="email" style="display:none" class="input-text " name="billing_email" id="billing_email" placeholder="">';
	}

	public static function maybe_set_customer_email() {
		$klarna_order = KCO_WC()->api->request_pre_get_order( WC()->session->get( 'kco_wc_order_id', true ) );
		$klarna_order = json_decode( $klarna_order['body'] );
		$email        = $klarna_order->billing_address->email;
		WC()->customer->set_billing_email( $email );

		return $email;
	}
} new Klarna_Checkout_For_Woocommerce_Checkout_Form_Fields();
