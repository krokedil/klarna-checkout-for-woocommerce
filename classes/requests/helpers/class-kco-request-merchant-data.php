<?php
/**
 * Merchant data processor.
 *
 * @package Klarna_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Request_Merchant_Data class.
 *
 * Class that gets the merchant data for the order.
 */
class KCO_Request_Merchant_Data {
	/**
	 * Gets merchant data for Klarna purchase.
	 *
	 * @return array
	 */
	public static function get_merchant_data() {
		$merchant_data = array();

		// Coupon info.
		foreach ( WC()->cart->get_applied_coupons() as $coupon ) {
			$merchant_data['coupons'][] = $coupon;
		}

		// Is user logged in.
		$merchant_data['is_user_logged_in'] = is_user_logged_in();

		return wp_json_encode( $merchant_data );
	}
}
