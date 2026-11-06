<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standard Webhooks signature verification.
 *
 * Kustom signs `{webhook-id}.{webhook-timestamp}.{body}` with the `whsec_` secret and
 * sends the result as one or more space separated `v1,<base64>` pairs, so that a
 * secret can be rotated without dropping deliveries.
 *
 * @link https://www.standardwebhooks.com/
 */
class WebhookSignature {

	/**
	 * How far a delivery's timestamp may be from ours, in seconds.
	 *
	 * @var int
	 */
	public const TOLERANCE = 300;

	/**
	 * The most a filter may widen the tolerance to, in seconds. Anything longer than
	 * the dedupe window in `Webhook` would let a recorded delivery be replayed.
	 *
	 * @var int
	 */
	public const MAX_TOLERANCE = 900;

	/**
	 * The signature scheme this verifier understands.
	 *
	 * @var string
	 */
	public const SCHEME = 'v1';

	/**
	 * Whether a delivery was signed with the merchant's secret.
	 *
	 * @param string $secret The `whsec_` signing secret.
	 * @param string $id The webhook-id header.
	 * @param string $timestamp The webhook-timestamp header.
	 * @param string $signature The webhook-signature header.
	 * @param string $payload The raw request body.
	 * @return true|\WP_Error
	 */
	public static function verify( $secret, $id, $timestamp, $signature, $payload ) {
		if ( '' === trim( (string) $secret ) ) {
			return new \WP_Error( 'no_secret', __( 'No webhook signing secret is configured.', 'klarna-checkout-for-woocommerce' ) );
		}

		if ( '' === trim( (string) $id ) || '' === trim( (string) $signature ) ) {
			return new \WP_Error( 'missing_headers', __( 'The delivery is not signed.', 'klarna-checkout-for-woocommerce' ) );
		}

		if ( ! self::is_fresh( $timestamp ) ) {
			return new \WP_Error( 'stale_timestamp', __( 'The delivery is too old to accept.', 'klarna-checkout-for-woocommerce' ) );
		}

		$expected = self::sign( $secret, "{$id}.{$timestamp}.{$payload}" );

		foreach ( explode( ' ', trim( $signature ) ) as $candidate ) {
			$parts = explode( ',', $candidate, 2 );

			if ( self::SCHEME === ( $parts[0] ?? '' ) && hash_equals( $expected, $parts[1] ?? '' ) ) {
				return true;
			}
		}

		return new \WP_Error( 'bad_signature', __( 'The delivery signature does not match.', 'klarna-checkout-for-woocommerce' ) );
	}

	/**
	 * Sign the content the scheme covers.
	 *
	 * @param string $secret The `whsec_` signing secret.
	 * @param string $content The signed content.
	 * @return string
	 */
	private static function sign( $secret, $content ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 is the signature encoding the scheme defines.
		return base64_encode( hash_hmac( 'sha256', $content, self::key( $secret ), true ) );
	}

	/**
	 * The raw signing key. The portal hands out a base64 key behind a `whsec_` prefix.
	 *
	 * @param string $secret The `whsec_` signing secret.
	 * @return string
	 */
	private static function key( $secret ) {
		$secret = trim( $secret );

		if ( 0 !== strpos( $secret, 'whsec_' ) ) {
			return $secret;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding the key the scheme defines, not obfuscation.
		$decoded = base64_decode( substr( $secret, 6 ), true );

		return false === $decoded ? substr( $secret, 6 ) : $decoded;
	}

	/**
	 * Whether the delivery's timestamp is close enough to ours to be replayed safely.
	 *
	 * @param string $timestamp The webhook-timestamp header, in unix seconds.
	 * @return bool
	 */
	private static function is_fresh( $timestamp ) {
		if ( ! is_numeric( $timestamp ) ) {
			return false;
		}

		/**
		 * How far a webhook's timestamp may be from the store's clock, in seconds.
		 *
		 * The tolerance is replay protection, so the value is clamped to
		 * `MAX_TOLERANCE`; a filter can tighten it, or loosen it for a store whose clock
		 * drifts, but cannot switch it off.
		 *
		 * @since 2.22.0
		 *
		 * @param int $tolerance The tolerance in seconds. Default 300.
		 */
		$tolerance = (int) apply_filters( 'kco_ipp_webhook_tolerance', self::TOLERANCE );
		$tolerance = max( 0, min( $tolerance, self::MAX_TOLERANCE ) );

		return abs( time() - (int) $timestamp ) <= $tolerance;
	}
}
