<?php
namespace Krokedil\KustomCheckout\Logging;

use KrokedilKlarnaCheckoutDeps\Krokedil\WpApi\FieldMasker;
use KrokedilKlarnaCheckoutDeps\Krokedil\WpApi\KeyMasker;

defined( 'ABSPATH' ) || exit;

/**
 * What the plugin masks out of its logs and the system status report.
 *
 * The configured rules describe the Kustom payloads we know about. The key names are the
 * safety net for everything else that reaches a log entry.
 */
class LogMasking {
	/**
	 * The address fields kept readable, since a rejected address is the most common thing
	 * a support case is about and none of these identify a person on their own.
	 */
	const ADDRESS_KEPT = array( 'postal_code', 'city', 'region', 'country' );

	/**
	 * The merchant URLs kept readable. The rest lead to the order received or pay page,
	 * and carry the order key, which grants access to the order.
	 */
	const MERCHANT_URLS_KEPT = array( 'terms', 'checkout', 'push', 'validation', 'notification', 'address_update', 'country_change', 'shipping_option_update' );

	/**
	 * Key names masked wherever they appear, on top of the package defaults.
	 *
	 * @var string[]
	 */
	private static $key_names = array(
		'given_name',
		'family_name',
		'organization_name',
		'email',
		'phone',
		'street_address',
		'care_of',
		'attention',
		'date_of_birth',
		'national_identification',
		'organization_registration_id',
		'vat_id',
		// Shipment tracking identifies a delivery, and with it a person.
		'tracking_number',
		'tracking_uri',
		// The snippet and the hosted page URLs are single use capabilities: whoever holds one can pay with it.
		'html_snippet',
		'redirect_url',
		'distribution_url',
		'qr_code_url',
		'signing_key',
	);

	/**
	 * Whether the key names have been handed to the package.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Widen the package key name masking with the names Kustom uses.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! self::$registered ) {
			KeyMasker::add_keys( self::$key_names );
			self::$registered = true;
		}
	}

	/**
	 * The rules for a Kustom request body.
	 *
	 * @return array
	 */
	public static function body_fields() {
		return array(
			'billing_address'  => array( FieldMasker::KEEP => self::ADDRESS_KEPT ),
			'shipping_address' => array( FieldMasker::KEEP => self::ADDRESS_KEPT ),
			'customer'         => array( FieldMasker::KEEP => array( 'type' ) ),
			'merchant_urls'    => array( FieldMasker::KEEP => self::MERCHANT_URLS_KEPT ),
			// Both are json encoded strings the rules cannot reach into. The merchant data holds the signing key.
			'merchant_data'    => 'mask',
			'attachment'       => 'mask',
		);
	}

	/**
	 * The rules for a whole set of request args.
	 *
	 * @return array
	 */
	public static function request_fields() {
		return array(
			'headers' => array( 'Authorization' ),
			'body'    => self::body_fields(),
		);
	}

	/**
	 * Mask a set of request args, decoding the json body so the rules can reach into it.
	 *
	 * @param array $request_args The request args.
	 * @return array|string The masked args, or the failure marker.
	 */
	public static function mask_request( $request_args ) {
		try {
			if ( isset( $request_args['body'] ) && is_string( $request_args['body'] ) ) {
				$decoded              = json_decode( $request_args['body'], true );
				$request_args['body'] = is_array( $decoded ) ? $decoded : $request_args['body'];
			}

			return FieldMasker::mask( $request_args, self::request_fields() );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask a decoded response body. Kustom answers with the same shape it is sent.
	 *
	 * @param mixed $body The decoded response body.
	 * @return mixed The masked body, or the failure marker.
	 */
	public static function mask_response( $body ) {
		if ( empty( $body ) || ! is_array( $body ) ) {
			return $body;
		}

		try {
			return FieldMasker::mask( $body, self::body_fields() );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask the recurring token Kustom addresses a customer by in the path. An order or
	 * session id is left readable, since neither authorises anything.
	 *
	 * @param string|null $request_url The request URL.
	 * @return string|null
	 */
	public static function mask_request_url( $request_url ) {
		if ( ! is_string( $request_url ) ) {
			return $request_url;
		}

		$masked = preg_replace( '#/tokens/[^/?]+#', '/tokens/' . KeyMasker::REDACTED, $request_url );
		return null === $masked ? KeyMasker::REDACTED : $masked;
	}

	/**
	 * Mask a finished log entry by key name, whatever its shape.
	 *
	 * @param mixed $entry The log entry.
	 * @return mixed The masked entry, or the failure marker.
	 */
	public static function mask_entry( $entry ) {
		// A failure here costs the entry, it never lets an unmasked one through.
		try {
			self::register();
			return KeyMasker::mask( $entry );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}
}
