<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request;

use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all In-Person Payments request classes.
 */
abstract class Request {
	/**
	 * The request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The request loggable title.
	 *
	 * @var string
	 */
	protected $log_title;

	/**
	 * The request arguments.
	 *
	 * @var array
	 */
	protected $arguments;

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args.
	 */
	public function __construct( $arguments = array() ) {
		$this->arguments = $arguments;
		$this->settings  = SettingsUtility::get_settings();
	}

	/**
	 * Get the full request URL.
	 *
	 * @return string
	 */
	abstract protected function get_request_url();

	/**
	 * Get the API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url_base() {
		$playground = SettingsUtility::is_testmode() ? '.playground' : '';
		return "https://api{$playground}.kustom.co/";
	}

	/**
	 * Whether this endpoint requires the x-merchant-id header.
	 *
	 * @return bool
	 */
	protected function needs_merchant_id_header() {
		return false;
	}

	/**
	 * Make the request.
	 *
	 * @return object|array|\WP_Error
	 */
	public function request() {
		$args = $this->get_request_args();
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$url      = $this->get_request_url();
		$response = wp_remote_request( $url, $args );

		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Builds the request args for a request.
	 *
	 * @return array|\WP_Error
	 */
	public function get_request_args() {
		$headers = $this->get_request_headers();
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$args = array(
			'headers'    => $headers,
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			/**
			 * How long an IPP request waits for Kustom, in seconds.
			 *
			 * The create-session call runs inside checkout, so a long timeout holds the
			 * salesperson's screen for that long.
			 *
			 * @since 2.22.0
			 *
			 * @param int $timeout The timeout in seconds. Default 10.
			 */
			'timeout'    => apply_filters( 'kco_ipp_request_timeout', 10 ),
		);

		$body = $this->get_body();
		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		return $args;
	}

	/**
	 * Get the request headers.
	 *
	 * @return array|\WP_Error
	 */
	protected function get_request_headers() {
		$auth = $this->calculate_auth();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$headers = array(
			'Authorization' => $auth,
			'Content-Type'  => 'application/json',
		);

		if ( $this->needs_merchant_id_header() ) {
			$headers['x-merchant-id'] = $this->get_auth_component( 'merchant_id' );
		}

		return $headers;
	}

	/**
	 * Get the user agent via filter 'http_headers_useragent'.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		return apply_filters(
			'http_headers_useragent',
			'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
			. ' - WooCommerce: ' . WC()->version
			. ' - IPP: ' . KCO_WC_VERSION
			. ' - PHP Version: ' . phpversion()
			. ' - Krokedil'
		);
	}

	/**
	 * Calculate basic auth for the request.
	 *
	 * @return string|\WP_Error
	 */
	protected function calculate_auth() {
		$merchant_id   = $this->get_auth_component( 'merchant_id' );
		$shared_secret = $this->get_auth_component( 'shared_secret' );

		if ( '' === $merchant_id || '' === $shared_secret ) {
			return new \WP_Error( 'missing_credentials', __( 'Kustom Checkout credentials are missing.', 'klarna-checkout-for-woocommerce' ) );
		}

		return 'Basic ' . base64_encode( $merchant_id . ':' . htmlspecialchars_decode( $shared_secret ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 used to calculate auth headers.
	}

	/**
	 * Gets an authentication component for the active environment.
	 *
	 * @param string $component_name What auth component to get from settings.
	 * @return string
	 */
	protected function get_auth_component( $component_name ) {
		$prefix = SettingsUtility::is_testmode() ? 'test_' : '';

		return $this->settings[ "{$prefix}{$component_name}" ] ?? '';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		return array();
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param array|\WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return object|array|\WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			$this->log_response( $response, $request_args, $request_url, 0 );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$processed_response = new \WP_Error( $response_code, $this->get_error_message( $body, $response_code ), "URL: {$request_url}" );
		} else {
			$processed_response = is_array( $body ) ? $body : array();
		}

		$this->log_response( $body, $request_args, $request_url, $response_code );

		return $processed_response;
	}

	/**
	 * Reads a human readable message out of the IPP problem-details error body.
	 *
	 * @param array|null $body The decoded response body.
	 * @param int        $response_code The HTTP status code.
	 * @return string
	 */
	protected function get_error_message( $body, $response_code ) {
		if ( ! is_array( $body ) ) {
			return sprintf( /* translators: %s: HTTP status code. */ __( 'Kustom API error %s.', 'klarna-checkout-for-woocommerce' ), $response_code );
		}

		$errors = $body['errors'] ?? array();
		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return implode( ' ', array_map( 'strval', $errors ) );
		}

		foreach ( array( 'detail', 'title' ) as $key ) {
			if ( ! empty( $body[ $key ] ) ) {
				return (string) $body[ $key ];
			}
		}

		return sprintf( /* translators: %s: HTTP status code. */ __( 'Kustom API error %s.', 'klarna-checkout-for-woocommerce' ), $response_code );
	}

	/**
	 * Response keys whose value is a credential and must never reach the log. The
	 * enrollment code pairs any device to the merchant account for up to 72 hours.
	 *
	 * @var string[]
	 */
	protected const REDACTED_RESPONSE_KEYS = array( 'enrollment_code' );

	/**
	 * Standardized logging format for requests/responses.
	 *
	 * @param mixed  $response The request response.
	 * @param array  $request_args The arguments of the request.
	 * @param string $request_url The request URL.
	 * @param int    $code The HTTP Response Code this request returned.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url, $code ) {
		// format_log() redacts Authorization, but not the merchant id we add ourselves.
		if ( isset( $request_args['headers']['x-merchant-id'] ) ) {
			$request_args['headers']['x-merchant-id'] = '[REDACTED]';
		}

		$log = \KCO_Logger::format_log( '', $this->method, $this->log_title, $request_args, $this->redact( $response ), $code, $request_url );
		\KCO_Logger::log( $log );
	}

	/**
	 * Replace the credentials in a response body, keeping the keys so the log still
	 * shows what came back.
	 *
	 * @param mixed $response The decoded response body.
	 * @return mixed
	 */
	protected function redact( $response ) {
		if ( ! is_array( $response ) ) {
			return $response;
		}

		foreach ( static::REDACTED_RESPONSE_KEYS as $key ) {
			if ( isset( $response[ $key ] ) ) {
				$response[ $key ] = '***';
			}
		}

		return $response;
	}
}
