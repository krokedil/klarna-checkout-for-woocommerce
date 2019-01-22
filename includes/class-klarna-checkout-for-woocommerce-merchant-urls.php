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
	public function get_urls() {
		$merchant_urls = array(
			'terms'                  => $this->get_terms_url(),                  // Required.
			'checkout'               => $this->get_checkout_url(),               // Required.
			'confirmation'           => $this->get_confirmation_url(),           // Required.
			'push'                   => $this->get_push_url(),                   // Required.
			'validation'             => $this->get_validation_url(),             // HTTPS.
			'shipping_option_update' => $this->get_shipping_option_update_url(), // HTTPS.
			// 'address_update'         => $this->get_address_update_url(),         // HTTPS.
			'notification'           => $this->get_notification_url(),
			// 'country_change'         => $this->get_country_change_url(),         // HTTPS.
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
	private function get_terms_url() {
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
	private function get_checkout_url() {
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
	private function get_confirmation_url() {
		$confirmation_url = wc_get_checkout_url() . '?confirm=yes&kco_wc_order_id={checkout.order.id}';
		return $confirmation_url;
	}

	/**
	 * Push URL.
	 *
	 * Required. URL of merchant confirmation page. Should be different than checkout and confirmation URLs.
	 *
	 * @return string
	 */
	private function get_push_url() {
		$session_id = $this->get_session_id();
		$push_url  = get_home_url() . '/wc-api/KCO_WC_Push/?kco-action=push&kco_wc_order_id={checkout.order.id}&kco_session_id=' . $session_id;
		return $push_url;
	}

	/**
	 * Validation URL.
	 *
	 * URL that will be requested for final merchant validation, must be https.
	 *
	 * @return string
	 */
	private function get_validation_url() {
		$session_id     = $this->get_session_id();
		$validation_url = get_home_url() . '/wc-api/KCO_WC_Validation/?kco-action=validation&kco_wc_order_id={checkout.order.id}&kco_session_id=' . $session_id;
		return str_replace( 'http:', 'https:', $validation_url );
	}

	/**
	 * Shipping option update URL.
	 *
	 * URL for shipping option update, must be https.
	 *
	 * @return string
	 */
	private function get_shipping_option_update_url() {
		$shipping_option_update_url = get_home_url() . '/wc-api/KCO_WC_Shipping_Option_Update/';
		return str_replace( 'http:', 'https:', $shipping_option_update_url );
	}

	/**
	 * Address update URL.
	 *
	 * URL for shipping, tax and purchase currency updates. Will be called on address changes, must be https.
	 *
	 * @return string
	 */
	private function get_address_update_url() {
		$address_update_url = get_home_url() . '/wc-api/KCO_WC_Address_Update/?kco_wc_order_id={checkout.order.id}';
		return str_replace( 'http:', 'https:', $address_update_url );
	}

	/**
	 * Notification URL.
	 *
	 * URL for notifications on pending orders.
	 *
	 * @return string
	 */
	private function get_notification_url() {
		$notification_url = get_home_url() . '/wc-api/KCO_WC_Notification/?kco_wc_order_id={checkout.order.id}';
		return $notification_url;
	}

	/**
	 * Country change URL.
	 *
	 * URL for shipping, tax and purchase currency updates. Will be called on purchase country changes, must be https.
	 *
	 * @return string
	 */
	private function get_country_change_url() {
		$country_change_url = get_home_url() . '/wp-json/kcowc/v1/address/{checkout.order.id}';
		return str_replace( 'http:', 'https:', $country_change_url );
	}

	/**
	 * Get session ID.
	 *
	 * Gets WooCommerce session ID. Used to send in merchant url's to Klarn.
	 * So we can retrieve the cart object in server to server calls from Klarna.
	 *
	 * @return string
	 */
	private function get_session_id() {
		foreach ( $_COOKIE as $key => $value ) {
			if ( strpos( $key, 'wp_woocommerce_session_' ) !== false ) {
				$session_id = explode( '||', $value );
				return $session_id[0];
			}
		}
	}
}
