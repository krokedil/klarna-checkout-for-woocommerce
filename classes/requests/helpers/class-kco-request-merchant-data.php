<?php
/**
 * Merchant data processor.
 *
 * @package Klarna_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Krokedil\KustomCheckout\Utility\SigningKeyUtility;

/**
 * KCO_Request_Merchant_Data class.
 *
 * Class that gets the merchant data for the order.
 */
class KCO_Request_Merchant_Data {
	/**
	 * Gets merchant data for Kustom purchase.
	 *
	 * @param int|null $order_id The WooCommerce order ID, if available.
	 * @return array
	 */
	public static function get_merchant_data( $order_id = null ) {
		$merchant_data = array();
		$order         = $order_id ? wc_get_order( $order_id ) : null;

		// Coupon info.
		foreach ( WC()->cart->get_applied_coupons() as $coupon ) {
			$merchant_data['coupons'][] = $coupon;
		}

		// Is user logged in.
		$merchant_data['is_user_logged_in'] = is_user_logged_in();
		$merchant_data['signing_key']       = SigningKeyUtility::from_wc_order_kco_id( $order ) ?? SigningKeyUtility::from_session_kco_id();

		return wp_json_encode( $merchant_data );
	}
}
