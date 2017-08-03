<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_Credentials class.
 *
 * Gets correct credentials based on customer country, store country and test/live mode.
 */
class Klarna_Checkout_For_WooCommerce_Credentials {

	/**
	 * Gets Klarna API credentials (merchant ID and shared secret) from user session.
	 */
	public static function get_credentials_from_session() {

	}

	/**
	 * Gets Klarna API credentials (merchant ID and shared secret) from a completed WC order.
	 */
	public static function get_credentials_from_order() {

	}

}