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
		$country_string = false;

		if ( 'US' === $base_location['country'] ) {
			$country_string = 'us';
		} else {
			// Use checkout billing country, if available, otherwise use store base location country for Klarna credentials.
			if ( WC()->checkout()->get_value( 'billing_country' ) ) {
				$checkout_country = WC()->checkout()->get_value( 'billing_country' );
			} else {
				$checkout_country = $base_location['country'];
			}

			switch ( $checkout_country ) {
				case 'AT':
					$country_string = 'at';
					break;
				case 'DK':
					$country_string = 'dk';
					break;
				case 'FI':
					$country_string = 'fi';
					break;
				case 'DE':
					$country_string = 'de';
					break;
				case 'NL':
					$country_string = 'nl';
					break;
				case 'NO':
					$country_string = 'no';
					break;
				case 'SE':
					$country_string = 'se';
					break;
				case 'GB':
					$country_string = 'gb';
					break;
			}
		}

		// No matching country found.
		if ( ! $country_string ) {
			return false;
		}

		$test_string = 'yes' === $this->settings['testmode'] ? 'test_' : '';

		$merchant_id = $this->settings[ $test_string . 'merchant_id_' . $country_string ];
		$shared_secret = $this->settings[ $test_string . 'shared_secret_' . $country_string ];

		// Merchant id and/or shared secret not found for matching country.
		if ( '' === $merchant_id || '' === $shared_secret ) {
			return false;
		}

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
