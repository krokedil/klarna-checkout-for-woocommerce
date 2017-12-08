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
		$this->settings = get_option( 'woocommerce_kco_settings' );
	}

	/**
	 * Gets Klarna API credentials (merchant ID and shared secret) from user session.
	 *
	 * @return bool|array $credentials
	 */
	public function get_credentials_from_session() {
		$base_location = wc_get_base_location();

		if ( 'US' === $base_location['country'] ) {
			$country_string = 'us';
		} else {
			$country_string = 'eu';
		}

		$test_string   = 'yes' === $this->settings['testmode'] ? 'test_' : '';
		$merchant_id   = $this->settings[ $test_string . 'merchant_id_' . $country_string ];
		$shared_secret = $this->settings[ $test_string . 'shared_secret_' . $country_string ];

		// Merchant id and/or shared secret not found for matching country.
		if ( '' === $merchant_id || '' === $shared_secret ) {
			return false;
		}

		$credentials = array(
			'merchant_id'   => $this->settings[ $test_string . 'merchant_id_' . $country_string ],
			'shared_secret' => htmlspecialchars_decode( $this->settings[ $test_string . 'shared_secret_' . $country_string ] ),
		);

		return $credentials;
	}

	/**
	 * Gets Klarna API credentials (merchant ID and shared secret) from a completed WC order.
	 */
	public function get_credentials_from_order() {

	}

}
