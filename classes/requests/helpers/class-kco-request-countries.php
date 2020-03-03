<?php
/**
 * Countries processor.
 *
 * @package Klarna_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Request_Countries class.
 *
 * Class that gets the billing and shipping countries for KCO.
 */
class KCO_Request_Countries {

	/**
	 * Gets the billing countries.
	 *
	 * @return array
	 */
	public static function get_billing_countries() {
		$wc_countries = new WC_Countries();

		return array_keys( $wc_countries->get_allowed_countries() );
	}

	/**
	 * Gets the shipping countries.
	 *
	 * @return array
	 */
	public static function get_shipping_countries() {
		$wc_countries = new WC_Countries();

		return array_keys( $wc_countries->get_shipping_countries() );
	}
}
