<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'kco_wc_show_snippet' ) ) {
	/**
	 * Echoes Klarna Checkout iframe snippet.
	 */
	function kco_wc_show_snippet() {
		$klarna_order = KCO_WC()->api->get_order();
		echo KCO_WC()->api->get_snippet( $klarna_order );
	}
}

if ( ! function_exists( 'kco_wc_show_order_notes' ) ) {
	/**
	 * Shows order notes field in Klarna Checkout page.
	 */
	function kco_wc_show_order_notes() {
		$order_fields = WC()->checkout()->get_checkout_fields( 'order' );
		$key = 'order_comments';
		if ( array_key_exists( $key, $order_fields ) ) {
			$order_notes_field = $order_fields[ $key ];
			woocommerce_form_field( $key, $order_notes_field, WC()->checkout()->get_value( $key ) );
		}
	}
}
