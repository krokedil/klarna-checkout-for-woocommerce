<?php
/**
 * Main request file.
 *
 * @package Klarna_Checkout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Requests class.
 *
 * The parent class for the different requests.
 */
class KCO_Request {

	/**
	 * Send sales tax as separate item (US merchants).
	 *
	 * @var bool
	 */
	protected $separate_sales_tax = false;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_kco_settings' );

		$base_location      = wc_get_base_location();
		$shop_country       = $base_location['country'];
		$this->shop_country = $shop_country;

		if ( 'US' === $this->shop_country ) {
			$this->separate_sales_tax = true;
		}
	}

	/**
	 * Gets Klarna API URL base.
	 */
	public function get_api_url_base() {
		$base_location  = wc_get_base_location();
		$country_string = 'US' === $base_location['country'] ? '-na' : '';
		$test_string    = 'yes' === $this->settings['testmode'] ? '.playground' : '';

		return 'https://api' . $country_string . $test_string . '.klarna.com/';
	}

	/**
	 * Gets Klarna API request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Authorization' => 'Basic ' . base64_encode( $this->get_merchant_id() . ':' . $this->get_shared_secret() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Gets Klarna merchant ID.
	 *
	 * @return string
	 */
	protected function get_merchant_id() {
		$credentials = KCO_WC()->credentials->get_credentials_from_session();

		return $credentials['merchant_id'];
	}

	/**
	 * Gets Klarna shared secret.
	 *
	 * @return string
	 */
	public function get_shared_secret() {
		$credentials = KCO_WC()->credentials->get_credentials_from_session();

		return $credentials['shared_secret'];
	}

	/**
	 * Gets Klarna API request headers.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		return apply_filters(
			'http_headers_useragent',
			'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
		) . ' - WooCommerce: ' . WC()->version . ' - KCO:' . KCO_WC_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';
	}

	/**
	 * Gets country for Klarna purchase.
	 *
	 * @return string
	 */
	protected function get_purchase_country() {
		// Try to use customer country if available.
		if ( null !== WC()->customer && method_exists( WC()->customer, 'get_billing_country' ) &&
			! empty( WC()->customer->get_billing_country() ) &&
			strlen( WC()->customer->get_billing_country() ) === 2
			) {
			return WC()->customer->get_billing_country( 'edit' );
		}

		$base_location = wc_get_base_location();
		$country       = $base_location['country'];

		return $country;
	}

	/**
	 * Checks response for any error.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return object|array
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		// Check if response is a WP_Error, and return it back if it is.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check the status code, if its not between 200 and 299 then its an error.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );
				foreach ( $errors['error_messages'] as $error ) {
					$error_message = $error_message . ' ' . $error;
				}
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $response['body'] . $error_message, $data );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
