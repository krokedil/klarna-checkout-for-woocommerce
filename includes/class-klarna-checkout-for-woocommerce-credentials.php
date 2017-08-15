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
	 * Klarna Checkout for WooCommerce settings.
	 *
	 * @var $settings
	 */
	public $settings = array();

	/**
	 * Klarna_Checkout_For_WooCommerce_Credentials constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_klarna_checkout_for_woocommerce_settings' );
	}

	/**
	 * Gets Klarna API credentials (merchant ID and shared secret) from user session.
	 *
	 * @return array $credentials
	 */
	public function get_credentials_from_session() {
		$base_location = wc_get_base_location();

		$country_string = 'US' === $base_location['country'] ? 'us' : 'eu';
		$test_string    = 'yes' === $this->settings['testmode'] ? 'test_' : '';

		$credentials = array(
			'merchant_id'   => $this->settings[ $test_string . 'merchant_id_' . $country_string ],
			'shared_secret' => $this->settings[ $test_string . 'shared_secret_' . $country_string ],
		);

		return $credentials;
	}

	/**
	 * Gets Klarna API credentials (merchant ID and shared secret) from a completed WC order.
	 */
	public function get_credentials_from_order() {

	}

}
