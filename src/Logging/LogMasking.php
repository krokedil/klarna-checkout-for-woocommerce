<?php
namespace Krokedil\KustomCheckout\Logging;

use KrokedilKlarnaCheckoutDeps\Krokedil\WpApi\FieldMasker;
use KrokedilKlarnaCheckoutDeps\Krokedil\WpApi\KeyMasker;

defined( 'ABSPATH' ) || exit;

/**
 * What the plugin masks out of its logs and the system status report.
 *
 * The field names come from the Kustom Checkout, Order Management, HPP and customer token
 * API schemas, and match exactly, so a setting such as options.phone_mandatory stays readable.
 */
class LogMasking {
	/**
	 * The address fields kept readable, since a rejected address is the most common thing a
	 * support case is about. The last two are the WooCommerce Store API spelling.
	 */
	const ADDRESS_KEPT = array( 'postal_code', 'city', 'region', 'country', 'postcode', 'state' );

	/**
	 * The payment_data entries the Store API order submit is sent whose value stays readable.
	 */
	const PAYMENT_DATA_KEPT = array( '_wc_klarna_checkout_flow', '_wc_klarna_order_id', '_wc_klarna_environment', '_wc_klarna_country', '_kco_recurring_order' );

	/**
	 * Field names masked wherever they appear.
	 *
	 * @var string[]
	 */
	private static $masked_fields = array(
		// Personal data, in addresses, the customer and the HPP session.
		'given_name',
		'family_name',
		'organization_name',
		'email',
		'billing_email',
		'phone',
		'street_address',
		'street_address2',
		'street_name',
		'street_number',
		'house_extension',
		'care_of',
		'attention',
		'buyer_reference',
		'delivery_instruction',
		'date_of_birth',
		'gender',
		'national_identification_number',
		'organization_registration_id',
		'vat_id',
		'contact_information',
		'manual_identification',
		// Shipment tracking identifies a delivery, and with it a person.
		'tracking_number',
		'tracking_uri',
		'return_tracking_number',
		'return_tracking_uri',
		// Free text the customer entered, or other plugins added to the Store API order.
		'user_input',
		'customer_note',
		'additional_fields',
		'extensions',
		// Single use capabilities: whoever holds one can pay with it or read the order.
		'html_snippet',
		'redirect_url',
		'distribution_url',
		'distribution_module',
		'qr_code_url',
		'manual_identification_check_url',
		'order_key',
		'signing_key',
		// Json encoded strings the rules cannot reach into. The merchant data holds the signing key.
		'merchant_data',
		'attachment',
	);

	/**
	 * The rules for a Kustom request or response body.
	 *
	 * @return array
	 */
	public static function body_fields() {
		return array_merge(
			self::$masked_fields,
			array(
				'billing_address'  => array( FieldMasker::KEEP => self::ADDRESS_KEPT ),
				'shipping_address' => array( FieldMasker::KEEP => self::ADDRESS_KEPT ),
				'customer'         => array( FieldMasker::KEEP => array( 'type', 'organization_entity_type' ) ),
			)
		);
	}

	/**
	 * The rules for a whole set of request args.
	 *
	 * @return array
	 */
	public static function request_fields() {
		return array(
			// The last three are sent with the Store API order submit.
			'headers' => array( 'Authorization', 'Nonce', 'Cart-Token' ),
			'cookies' => 'mask',
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

			if ( isset( $request_args['body'] ) && is_array( $request_args['body'] ) ) {
				$request_args['body'] = self::mask_payment_data( $request_args['body'] );
			}

			return self::mask_strings( FieldMasker::mask( $request_args, self::request_fields() ) );
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
			return self::mask_strings( FieldMasker::mask( $body, self::body_fields() ) );
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
		return null === $masked ? KeyMasker::REDACTED : self::mask_strings( $masked );
	}

	/**
	 * Mask a finished log entry, whatever its shape, including a plain message.
	 *
	 * @param mixed $entry The log entry.
	 * @return mixed The masked entry, or the failure marker.
	 */
	public static function mask_entry( $entry ) {
		// A failure here costs the entry, it never lets an unmasked one through.
		try {
			if ( is_array( $entry ) ) {
				$entry = FieldMasker::mask( $entry, self::body_fields() );
			}

			return self::mask_strings( KeyMasker::mask( $entry ) );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask the payment_data values the Store API order submit carries, which are named by
	 * a sibling key rather than their own, such as the shipping email and the recurring token.
	 *
	 * @param array $body The decoded request body.
	 * @return array
	 */
	private static function mask_payment_data( $body ) {
		if ( ! isset( $body['payment_data'] ) || ! is_array( $body['payment_data'] ) ) {
			return $body;
		}

		foreach ( $body['payment_data'] as $index => $entry ) {
			if ( is_array( $entry ) && array_key_exists( 'value', $entry ) && ! in_array( $entry['key'] ?? null, self::PAYMENT_DATA_KEPT, true ) ) {
				$body['payment_data'][ $index ]['value'] = KeyMasker::placeholder( $entry['value'] );
			}
		}

		return $body;
	}

	/**
	 * Mask an order key or an email address by its shape, wherever it sits in a string. This
	 * is what catches them in a URL or a plain message, where no field name describes them.
	 *
	 * @param mixed $data The data to mask.
	 * @return mixed
	 */
	private static function mask_strings( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( __CLASS__, 'mask_strings' ), $data );
		}

		if ( ! is_string( $data ) ) {
			return $data;
		}

		$masked = preg_replace(
			array( '/wc_order_[A-Za-z0-9]+/', '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' ),
			KeyMasker::REDACTED,
			$data
		);

		return null === $masked ? KeyMasker::REDACTED : $masked;
	}
}
