<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_Additional_Fields class.
 *
 * Displays additional checkout fields in KCO page.
 */
class Klarna_Checkout_For_WooCommerce_Additional_Fields {

	/**
	 * Array of fields returned by KCO iframe.
	 */
	private function fields_returned_by_klarna() {
		return array(
			'billing' => array(
				'billing_first_name',
				'billing_last_name',
				'billing_country',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_phone',
				'billing_email',
			),
			'shipping' => array(
				'shipping_first_name',
				'shipping_last_name',
				'shipping_country',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
			),
		);
	}

	public function get_remaining_fields() {
		$default_fields = WC()->checkout()->get_fields();
	}
}
