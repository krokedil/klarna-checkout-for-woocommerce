<?php
/**
 * Main request class
 *
 * @package WC_Klarna_Order_Management/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all request classes.
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
	 * The Klarna order id.
	 *
	 * @var string
	 */
	protected $klarna_order_id;

	/**
	 * The Klarna order object.
	 *
	 * @var object
	 */
	protected $klarna_order;

	/**
	 * The WC order id.
	 *
	 * @var int
	 */
	protected $order_id;

	/**
	 * The request arguments.
	 *
	 * @var array
	 */
	protected $arguments;

	/**
	 * The plugin settings for the order.
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
		$this->arguments       = $arguments;
		$this->order_id        = $arguments['order_id'];
		$this->settings        = $this->get_settings();
		$this->klarna_order_id = $this->get_klarna_order_id();
		$this->klarna_order    = array_key_exists( 'klarna_order', $arguments ) ? $arguments['klarna_order'] : false;
	}

	/**
	 * Returns the settings for the plugin based on the orders payment method.
	 *
	 * @return array
	 */
	private function get_settings() {
		return WC_Klarna_Order_Management::get_instance()->settings->get_settings( $this->order_id );
	}

	/**
	 * Get which klarna plugin is relevant for this request. Returns false if no Klarna variant seems relevant.
	 *
	 * @return bool|string
	 */
	protected function get_klarna_variant() {
		$order = wc_get_order( $this->order_id );

		if ( ! $order ) {
			return false;
		}

		$payment_method = $order->get_payment_method();
		switch ( $payment_method ) {
			case 'klarna_payments':
			case 'kco':
				return $payment_method;
		}

		return false;
	}

	/**
	 * Gets Klarna order ID from WooCommerce order.
	 *
	 * @return mixed
	 */
	public function get_klarna_order_id() {
		$order = wc_get_order( $this->order_id );
		return ! empty( $order->get_transaction_id() ) ? $order->get_transaction_id() : $order->get_meta( '_wc_klarna_order_id', true );
	}

	/**
	 * Gets API region code for Klarna
	 *
	 * @return string
	 */
	protected function get_klarna_api_region() {
		$country = $this->get_klarna_country();
		switch ( $country ) {
			case 'CA':
			case 'US':
				return '-na';
			case 'AU':
			case 'NZ':
				return '-oc';
			default:
				return '';
		}
	}

	/**
	 * Get the country code for the underlaying order.
	 *
	 * @return string
	 */
	protected function get_klarna_country() {
		$order   = wc_get_order( $this->order_id );
		$country = $order->get_meta( '_wc_klarna_country', true );
		return $country ? $country : '';
	}

	/**
	 * Get the domain to use for the request.
	 *
	 * @param string $klarna_variant The Klarna variant to use (e.g., 'klarna_payments', 'kco').
	 *
	 * @return string The domain to use for the request.
	 */
	public static function get_api_domain( $klarna_variant = 'klarna_payments' ) {
		// If the klarna variant is not KCO, return klarna.com.
		if ( 'kco' !== $klarna_variant ) {
			return 'klarna.com';
		}

		// If the variant is KCO, return kustom.co.
		return 'kustom.co';
	}

	/**
	 * Get the API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url_base() {
		$region     = strtolower( apply_filters( 'klarna_base_region', $this->get_klarna_api_region() ) );
		$playground = $this->use_playground() ? '.playground' : '';
		$domain     = self::get_api_domain( $this->get_klarna_variant() );
		return "https://api{$region}{$playground}.{$domain}/";
	}

	/**
	 * Get the full request URL.
	 *
	 * @return string
	 */
	abstract protected function get_request_url();

	/**
	 * Make the request.
	 *
	 * @return object|WP_Error
	 */
	public function request() {
		$url  = $this->get_request_url();
		$args = $this->get_request_args();
		if ( is_wp_error( $args ) || ( isset( $args['body'] ) && is_null( json_decode( $args['body'] ) ) ) ) {
			return is_wp_error( $args ) ? $args : new WP_Error( 'invalid_json', __( 'Invalid JSON response from the server.', 'woocommerce' ) );
		}
		$response = wp_remote_request( $url, $args );
		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Get the request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		$auth = $this->calculate_auth();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		return array(
			'Authorization' => $auth,
			'Content-Type'  => 'application/json',
		);
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
			. ' - OM: ' . WC_KLARNA_ORDER_MANAGEMENT_VERSION
			. ' - PHP Version: ' . phpversion()
			. ' - Krokedil'
		);
	}

	/**
	 * Get if this order should use the Klarna Playground or not.
	 *
	 * @return bool
	 */
	protected function use_playground() {
		$playground = false;
		$variant    = $this->get_klarna_variant();
		if ( $variant ) {
			$payment_method_settings = get_option( "woocommerce_{$variant}_settings" );
			if ( ! $payment_method_settings || 'yes' == $payment_method_settings['testmode'] ) {
				$playground = true;
			}
		}
		return $playground;
	}

	/**
	 * Calculate basic auth for the request.
	 *
	 * @return string|WP_Error
	 */
	protected function calculate_auth() {
		$variant = $this->get_klarna_variant();
		if ( ! $variant ) {
			return new WP_Error( 'wrong_gateway', 'This order was not create via Klarna Payments or Klarna Checkout for WooCommerce.' );
		}
		$gateway_title = 'kco' === $variant ? 'Klarna Checkout' : 'Klarna Payments';

		$merchant_id   = $this->get_auth_component( 'merchant_id' );
		$shared_secret = $this->get_auth_component( 'shared_secret' );
		if ( '' === $merchant_id || '' === $shared_secret ) {
			return new WP_Error( 'missing_credentials', "{$gateway_title} credentials are missing" );
		}
		return 'Basic ' . base64_encode( $merchant_id . ':' . htmlspecialchars_decode( $shared_secret ) );
	}

	/**
	 * Gets the Merchant ID for this request.
	 *
	 * @param string $component_name What auth component to get from settings.
	 * @return string
	 */
	protected function get_auth_component( $component_name ) {
		$order     = wc_get_order( $this->order_id );
		$component = $order->get_meta( "_wc_klarna_{$component_name}", true );
		if ( ! empty( $component ) ) {
			return iconv( mb_detect_encoding( $component, mb_detect_order(), true ), 'UTF-8', $component );
		}

		$variant = $this->get_klarna_variant();
		if ( empty( $variant ) ) {
			return '';
		}
		$options = get_option( "woocommerce_{$variant}_settings" );
		if ( ! $options ) {
			return '';
		}

		$prefix  = $this->use_playground() ? 'test_' : '';
		$country = $this->get_klarna_country();
		if ( 'klarna_payments' === $variant ) {
			$country_string = strtolower( $country );
		} elseif ( 'US' === $country ) {
				$country_string = 'us';
		} else {
			$country_string = 'eu';
		}

		$key = "{$prefix}{$component_name}_{$country_string}";

		if ( key_exists( $key, $options ) ) {
			return $options[ $key ];
		}
		return '';
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return array|WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $response_code < 200 || $response_code >= 300 ) { // Anything not in the 200 range is an error.
			$data          = "URL: {$request_url} - " . wp_json_encode( $request_args );
			$error_message = "API Error {$response_code}";

			if ( null !== $body && property_exists( $body, 'error_messages' ) ) {
				$error_message = join( ' ', $body->error_messages );
			}
			$processed_response = new WP_Error( $response_code, $error_message, $data );
		} else { // Response is *not* an error!
			$processed_response = $body;

			// On capture, the $body is null and capture id is sent in the HTTP headers.
			if ( isset( $this->arguments['request'] ) && 'capture' === $this->arguments['request'] ) {
				$processed_response = sanitize_key( $response['headers']->offsetGet( 'capture-id' ) );
			}
		}

		$this->log_response( $response, $request_args, $response_code );
		return $processed_response;
	}

	/**
	 * Builds the request args for a request.
	 *
	 * @return array|WP_Error
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
			'timeout'    => apply_filters( 'kom_request_timeout', 10 ),
		);
		$body = $this->get_body();
		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}
		return $args;
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
	 * Standardized logging format for requests/responses.
	 *
	 * @param array|WP_Error $response The request response.
	 * @param array          $request_args The arguments of the request.
	 * @param int            $code The HTTP Response Code this request returned.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $code ) {
		foreach ( $request_args['headers'] as $header => $value ) {
			if ( 'authorization' === strtolower( $header ) ) {
				// If it is longer than 15 char., it most likely has a token. This is an assumption that is safe even if it is wrong.
				$request_args['headers'][ $header ] = strlen( $value ) > 15 ? '[REDACTED]' : '[MISSING]';
				break;
			}
		}
		$log = WC_Klarna_Logger::format_log( $this->klarna_order_id, $this->method, $this->log_title, $request_args, $response, $code );
		WC_Klarna_Logger::log( $log, $this->order_id );
	}
}
