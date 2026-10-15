<?php
namespace Krokedil\KustomCheckout\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KeyValidator class.
 *
 * Verifies a Kustom Elements public API key against the Elements API.
 */
class KeyValidator {
	const STATUS_OK                 = 'ok';
	const STATUS_MISSING            = 'missing';
	const STATUS_INVALID_KEY        = 'invalid_key';
	const STATUS_ORIGIN_NOT_ALLOWED = 'origin_not_allowed';
	const STATUS_UNKNOWN            = 'unknown';

	/**
	 * Get the cached status of a public API key, validating it if there is no cached result.
	 *
	 * @param string $key      The public API key.
	 * @param bool   $testmode Whether the key is for the test (playground) environment.
	 * @param bool   $refresh  Whether to skip the cache and validate again.
	 * @return string One of the STATUS_* constants.
	 */
	public static function get_status( $key, $testmode, $refresh = false ) {
		if ( empty( $key ) ) {
			return self::STATUS_MISSING;
		}

		$transient = self::get_transient_name( $key, $testmode );
		$status    = $refresh ? false : get_transient( $transient );
		if ( is_string( $status ) && '' !== $status ) {
			return $status;
		}

		$status = self::validate( $key, $testmode );

		// Don't let a temporary network error stick around, recheck it sooner than a definitive result.
		$expiration = self::STATUS_UNKNOWN === $status ? 15 * MINUTE_IN_SECONDS : DAY_IN_SECONDS;
		set_transient( $transient, $status, $expiration );

		return $status;
	}

	/**
	 * Validate a public API key against the Elements API, without using the cache.
	 *
	 * @param string $key      The public API key.
	 * @param bool   $testmode Whether the key is for the test (playground) environment.
	 * @return string One of the STATUS_* constants.
	 */
	public static function validate( $key, $testmode ) {
		if ( empty( $key ) ) {
			return self::STATUS_MISSING;
		}

		$url = add_query_arg(
			array(
				// The API requires a valid locale, and the result doesn't depend on it.
				'locale'    => 'en-US',
				'client_id' => rawurlencode( $key ),
			),
			self::get_api_base( $testmode ) . 'placements/AVAILABLE_PAYMENT_METHODS'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array(
					// The API allows or refuses the request based on the Origin, like it does for the browser.
					'Origin' => self::get_origin(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::STATUS_UNKNOWN;
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			return self::STATUS_OK;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = is_array( $body ) ? ( $body['code'] ?? '' ) : '';

		switch ( $code ) {
			case 'CLIENT_NOT_FOUND':
				return self::STATUS_INVALID_KEY;
			case 'ORIGIN_NOT_ALLOWED':
				return self::STATUS_ORIGIN_NOT_ALLOWED;
			default:
				return self::STATUS_UNKNOWN;
		}
	}

	/**
	 * Get a merchant facing message for a status.
	 *
	 * @param string $status   One of the STATUS_* constants.
	 * @param bool   $testmode Whether the status is for the test (playground) environment.
	 * @return string
	 */
	public static function get_message( $status, $testmode ) {
		$environment = $testmode
			? __( 'test', 'klarna-checkout-for-woocommerce' )
			: __( 'production', 'klarna-checkout-for-woocommerce' );

		switch ( $status ) {
			case self::STATUS_OK:
				// translators: %s: The site domain, e.g. https://example.com.
				return sprintf( __( 'Connected. Kustom Elements is allowed on %s.', 'klarna-checkout-for-woocommerce' ), self::get_origin() );
			case self::STATUS_MISSING:
				return __( 'Not configured. Add a public API key to use Kustom Elements.', 'klarna-checkout-for-woocommerce' );
			case self::STATUS_INVALID_KEY:
				return sprintf(
					// translators: %s: The environment (production or test).
					__( 'The %s public API key was not recognized by Kustom. Copy the data-public-api-key from Elements → Installation script in the Kustom Portal.', 'klarna-checkout-for-woocommerce' ),
					$environment
				);
			case self::STATUS_ORIGIN_NOT_ALLOWED:
				return sprintf(
					// translators: 1: The environment (production or test), 2: The site domain, e.g. https://example.com.
					__( 'The %1$s public API key is valid, but %2$s is not an allowed domain. Add the domain under Elements in the Kustom Portal.', 'klarna-checkout-for-woocommerce' ),
					$environment,
					self::get_origin()
				);
			default:
				return __( 'The public API key could not be verified right now. Kustom Elements may still work, the check will be retried later.', 'klarna-checkout-for-woocommerce' );
		}
	}

	/**
	 * Whether a status means the elements will not display.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @return bool
	 */
	public static function is_error( $status ) {
		return in_array( $status, array( self::STATUS_INVALID_KEY, self::STATUS_ORIGIN_NOT_ALLOWED ), true );
	}

	/**
	 * Get the origin (scheme, host and port) of the site, as the browser sends it to the Elements API.
	 *
	 * @return string
	 */
	public static function get_origin() {
		$parts = wp_parse_url( home_url() );
		if ( empty( $parts['host'] ) ) {
			return '';
		}

		$origin = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		return $origin;
	}

	/**
	 * Get the Elements API base URL for an environment.
	 *
	 * @param bool $testmode Whether to use the test (playground) environment.
	 * @return string
	 */
	private static function get_api_base( $testmode ) {
		$default_base = $testmode
			? 'https://js.playground.kustom.co/kustom-elements-api/v1/'
			: 'https://js.live.kustom.co/kustom-elements-api/v1/';

		/**
		 * Filter the Kustom Elements API base URL used to validate the public API key.
		 *
		 * @param string $default_base The API base URL, with a trailing slash.
		 * @param bool   $testmode     Whether the test (playground) environment is used.
		 */
		return trailingslashit( apply_filters( 'kco_elements_api_base', $default_base, $testmode ) );
	}

	/**
	 * Get the transient name for a key's status. Includes the origin, so a changed site URL is revalidated.
	 *
	 * @param string $key      The public API key.
	 * @param bool   $testmode Whether the key is for the test (playground) environment.
	 * @return string
	 */
	private static function get_transient_name( $key, $testmode ) {
		return 'kco_elements_key_status_' . md5( $key . '|' . self::get_origin() . '|' . ( $testmode ? 'test' : 'live' ) );
	}
}
