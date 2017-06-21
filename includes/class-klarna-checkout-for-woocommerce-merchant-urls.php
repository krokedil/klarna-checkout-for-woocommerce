<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_Merchant_URLs class.
 *
 * Class that formats gets merchant URLs Klarna API.
 */
class Klarna_Checkout_For_WooCommerce_Merchant_URLs {

	/**
	 * Gets formatted merchant URLs array.
	 *
	 * @return array
	 */
	public static function get_urls() {
		$merchant_urls = array(
			'terms'                  => self::get_terms_url(),        // Required.
			'checkout'               => self::get_checkout_url(),     // Required.
			'confirmation'           => self::get_confirmation_url(), // Required.
			'push'                   => self::get_push_url(),         // Required.
			'validation'             => self::get_validation_url(),
			'shipping_option_update' => self::get_shipping_option_update_url(),
			'address_update'         => self::get_address_update_url(),
			'notification'           => self::get_notification_url(),
			'country_change'         => self::get_country_change_url(),
		);

		return $merchant_urls;
	}

	/**
	 * Terms URL.
	 *
	 * Required. URL of merchant terms and conditions. Should be different than checkout, confirmation and push URLs.
	 *
	 * @return string
	 */
	private static function get_terms_url() {
		$terms_url = get_permalink( wc_get_page_id( 'terms' ) );
		return $terms_url;
	}

	/**
	 * Checkout URL.
	 *
	 * Required. URL of merchant checkout page. Should be different than terms, confirmation and push URLs.
	 *
	 * @return string
	 */
	private static function get_checkout_url() {
		$checkout_url = wc_get_checkout_url();
		return $checkout_url;
	}

	/**
	 * Confirmation URL.
	 *
	 * Required. URL of merchant confirmation page. Should be different than checkout and confirmation URLs.
	 *
	 * @return string
	 */
	private static function get_confirmation_url() {
		$confirmation_url = '';
		return $confirmation_url;
	}

	/**
	 * Push URL.
	 *
	 * Required. URL of merchant confirmation page. Should be different than checkout and confirmation URLs.
	 *
	 * @return string
	 */
	private static function get_push_url() {
		$push_url = '';
		return $push_url;
	}

	/**
	 * Validation URL.
	 *
	 * URL that will be requested for final merchant validation, must be https.
	 *
	 * @return string
	 */
	private static function get_validation_url() {
		$validation_url = '';
		return $validation_url;
	}

	/**
	 * Shipping option update URL.
	 *
	 * URL for shipping option update, must be https.
	 *
	 * @return string
	 */
	private static function get_shipping_option_update_url() {
		$shipping_option_update_url = '';
		return $shipping_option_update_url;
	}

	/**
	 * Address update URL.
	 *
	 * URL for shipping, tax and purchase currency updates. Will be called on address changes, must be https.
	 *
	 * @return string
	 */
	private static function get_address_update_url() {
		$address_update_url = '';
		return $address_update_url;
	}

	/**
	 * Notification URL.
	 *
	 * URL for notifications on pending orders.
	 *
	 * @return string
	 */
	private static function get_notification_url() {
		$notification_url = '';
		return $notification_url;
	}

	/**
	 * Country change URL.
	 *
	 * URL for shipping, tax and purchase currency updates. Will be called on purchase country changes, must be https.
	 *
	 * @return string
	 */
	private static function get_country_change_url() {
		$country_change_url = '';
		return $country_change_url;
	}

}