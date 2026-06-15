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
	 * The plugin settings.
	 *
	 * @var array|false
	 */
	protected $settings;

	/**
	 * The country of the store's base location.
	 *
	 * @var string
	 */
	protected $shop_country;

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
	 * Gets Kustom API URL base.
	 */
	public function get_api_url_base() {
		$test_string = 'yes' === $this->settings['testmode'] ? '.playground' : '';
		return "https://api{$test_string}.kustom.co/";
	}

	/**
	 * Gets Kustom API request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
			return array(
				'Authorization'  => 'Basic ' . base64_encode( $this->get_merchant_id() . ':' . $this->get_shared_secret() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
				'Content-Type'   => 'application/json',
				'kustom-partner' => 'PG000651',
			);
	}

	/**
	 * Gets Kustom merchant ID.
	 *
	 * @return string
	 */
	protected function get_merchant_id() {
		$credentials = KCO_WC()->credentials->get_credentials_from_session();

		return $credentials['merchant_id'];
	}

	/**
	 * Gets Kustom shared secret.
	 *
	 * @return string
	 */
	protected function get_shared_secret() {
		$credentials = KCO_WC()->credentials->get_credentials_from_session();

		return $credentials['shared_secret'];
	}

	/**
	 * Gets the user agent for the API call.
	 *
	 * @param string $url The request URL.
	 * @return string
	 */
	protected function get_user_agent( $url = '' ) {
		return apply_filters(
			'http_headers_useragent',
			'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			empty( $url ) ? $this->get_api_url_base() : $url
		) . ' - WooCommerce: ' . WC()->version . ' - KCO:' . KCO_WC_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';
	}

	/**
	 * Gets country for Kustom purchase.
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

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		// Check the status code, if its not between 200 and 299 then its an error.
		if ( $code < 200 || $code > 299 ) {
			$data          = "URL: {$request_url} - " . wp_json_encode( $this->sanitize_request_args( $request_args ) );
			$error_message = '';
			// Get the error messages.
			$errors = json_decode( $body, true );
			if ( empty( $errors ) ) {
				// No customer facing message while we silently retry. Blocks print the WP_Error message, so keep it empty until we give up.
				$message = '';

				// Both the classic and block checkout reload on this flag, limit the reloads so a persistent empty body can't loop forever.
				$reload_attempts = (int) WC()->session->get( 'kco_empty_body_reloads', 0 );

				// It typically requires three reloads for a session to be properly set up. Hence why we picked three attempts before giving up and showing the error message to the customer.
				if ( $reload_attempts < 3 ) {
					// Likely a transient empty body, reload the checkout and retry instead of failing.
					WC()->session->set( 'kco_empty_body_reloads', $reload_attempts + 1 );
					WC()->session->set( 'reload_checkout', true );
				} else {
					// The empty body persisted across reloads, stop reloading and show a customer facing message.
					KCO_Logger::log( "Received empty body from Kustom after {$reload_attempts} checkout reloads. URL: {$request_url}" );
					// Blocks print the WP_Error message themselves, so only set it for the classic checkout to keep it hidden in blocks.
					if ( ! WC()->is_rest_api_request() ) {
						$message = __( 'The payment provider is temporarily unavailable. Please wait a moment and try again.', 'klarna-checkout-for-woocommerce' );
						if ( ! wc_has_notice( $message, 'error' ) ) {
							wc_add_notice( $message, 'error' );
						}
					}
				}

				// The error code identifies the empty body case, the message is the customer facing text.
				return new WP_Error( 'received_empty_body', $message, $data );
			} elseif ( isset( $errors['error_messages'] ) && is_array( $errors['error_messages'] ) ) {
				foreach ( $errors['error_messages'] as $error ) {
					$error_message = "$error_message  $error";
				}
			}
			return new WP_Error( $code, "$body $error_message", $data );
		}

		// Successful response, reset the empty body reload counter.
		WC()->session->set( 'kco_empty_body_reloads', 0 );

		// Kustom responded, clear any empty body notice that was shown before the checkout recovered.
		$message = __( 'The payment provider is temporarily unavailable. Please wait a moment and try again.', 'klarna-checkout-for-woocommerce' );
		if ( wc_has_notice( $message, 'error' ) ) {
			$error_notices = wc_get_notices( 'error' );
			foreach ( $error_notices as $key => $notice ) {
				if ( ( $notice['notice'] ?? '' ) === $message ) {
					unset( $error_notices[ $key ] );
				}
			}
			$all_notices          = wc_get_notices();
			$all_notices['error'] = array_values( $error_notices );
			wc_set_notices( $all_notices );
		}

		return json_decode( $body, true );
	}

	/**
	 * Remove sensitive data from the log.
	 *
	 * @param array $request_args The request data to sanitize.
	 * @return array The request data sanitized.
	 */
	protected function sanitize_request_args( $request_args ) {
		// Do not log the authorization token.
		foreach ( $request_args['headers'] as $header => $value ) {
			if ( 'authorization' === strtolower( $header ) ) {
				// If it is longer than 15 char., it most likely has a token. This is an assumption that is safe even if it is wrong.
				$request_args['headers'][ $header ] = strlen( $value ) > 15 ? '[REDACTED]' : '[MISSING]';
				break;
			}
		}

		return $request_args;
	}
}
