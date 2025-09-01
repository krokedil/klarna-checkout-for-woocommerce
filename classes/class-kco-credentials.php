<?php
/**
 * File for Credentials class.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Credentials class.
 *
 * Gets correct credentials based on customer country, store country and test/live mode.
 */
class KCO_Credentials {

	/**
	 * Kustom Checkout for WooCommerce settings.
	 *
	 * @var $settings
	 */
	public $settings = array();

	/**
	 * KCO_Credentials constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_kco_settings', array() );
		add_filter( 'kco_api_domain', array( $this, 'maybe_set_api_domain' ) );
	}

	/**
	 * Uses the setting to see if we should force the API domain or let the automatic function handle it.
	 *
	 * @param string $api_domain The API domain to use.
	 *
	 * @return string The domain to use for the request.
	 */
	public function maybe_set_api_domain( $api_domain ) {
		$settings           = get_option( 'woocommerce_kco_settings', array() );
		$api_domain_setting = $settings['api_domain'] ?? '';

		// If the setting is empty, use the value passed in the filter.
		if ( empty( $api_domain_setting ) ) {
			return $api_domain;
		}

		// If the setting is not empty, use the value from the setting.
		return $api_domain_setting;
	}

	/**
	 * Gets Kustom API credentials (merchant ID and shared secret) from user session.
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
			'shared_secret' => htmlspecialchars_decode( $this->settings[ $test_string . 'shared_secret_' . $country_string ], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ),
		);

		return apply_filters( 'kco_wc_credentials_from_session', $credentials, $this->settings['testmode'] );
	}
}
