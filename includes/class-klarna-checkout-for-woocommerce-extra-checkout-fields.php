<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Extra_Checkout_Fields class.
 *
 * Class that handles extra checkout fields displayed when Klarna Checkout is the selected payment option.
 */
class Klarna_Checkout_For_WooCommerce_Extra_Checkout_Fields {

	/**
	 * Returns array of all WooCommerce Checkout fields.
	 */
	public function get_all_checkout_fields() {
		$default_billing_fields  = WC()->checkout()->get_checkout_fields( 'billing' );
		$default_shipping_fields = WC()->checkout()->get_checkout_fields( 'shipping' );
		$default_account_fields  = WC()->checkout()->get_checkout_fields( 'account' );
		$default_order_fields    = WC()->checkout()->get_checkout_fields( 'order' );

		return array(
			'billing'  => $default_billing_fields,
			'shipping' => $default_shipping_fields,
			'account'  => $default_account_fields,
			'order'    => $default_order_fields,
		);
	}

	/**
	 * Returns list of WooCommerce checkout fields that can be populated using information from Klarna.
	 */
	public function get_klarna_checkout_fields() {
		$klarna_fields = array(
			'billing'  => array(
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

		return apply_filters( 'kco_wc_klarna_checkout_fields', $klarna_fields );
	}

	/**
	 * Returns list of WooCommerce checkout fields that can NOT be populated using information from Klarna.
	 */
	public function get_remaining_checkout_fields() {
		$all_fields    = $this->get_all_checkout_fields();
		$klarna_fields = $this->get_klarna_checkout_fields();

		foreach ( $klarna_fields['billing'] as $field ) {
			if ( array_key_exists( $field, $all_fields['billing'] ) ) {
				unset( $all_fields['billing'][ $field ] );
			}

			unset( $all_fields['billing']['billing_company'] ); // B2C only for now.
		}

		foreach ( $klarna_fields['shipping'] as $field ) {
			if ( array_key_exists( $field, $all_fields['shipping'] ) ) {
				unset( $all_fields['shipping'][ $field ] );
			}

			unset( $all_fields['shipping']['shipping_company'] ); // B2C only for now.
		}

		return $all_fields;
	}
}
