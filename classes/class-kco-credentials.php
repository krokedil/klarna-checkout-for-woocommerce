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
 * Gets correct credentials based on test/live mode.
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
	}

	/**
	 * Gets Kustom API credentials (merchant ID and shared secret) from user session.
	 *
	 * @return bool|array $credentials
	 */
	public function get_credentials_from_session() {
		$testmode      = $this->settings['testmode'] ?? 'no';
		$test_string   = 'yes' === $testmode ? 'test_' : '';
		$merchant_id   = $this->settings[ $test_string . 'merchant_id' ] ?? '';
		$shared_secret = $this->settings[ $test_string . 'shared_secret' ] ?? '';

		// Merchant id and/or shared secret not found for matching country.
		if ( '' === $merchant_id || '' === $shared_secret ) {
			return false;
		}

		$credentials = array(
			'merchant_id'   => $merchant_id,
			'shared_secret' => htmlspecialchars_decode( $shared_secret, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ),
		);

		return apply_filters( 'kco_wc_credentials_from_session', $credentials, $testmode );
	}
}
