<?php
namespace Krokedil\KustomCheckout\OrderManagement\Webhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SignatureVerifier class.
 *
 * Verifies webhook requests from Kustom, which are signed according to the Standard Webhooks specification.
 *
 * @see https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
 */
class SignatureVerifier {
	/**
	 * How far the webhook timestamp may deviate from the current time, in seconds. Protects against replayed requests.
	 */
	const TIMESTAMP_TOLERANCE = 300;

	/**
	 * The signing secrets to verify against.
	 *
	 * @var string[]
	 */
	protected $secrets;

	/**
	 * Class constructor.
	 *
	 * @param string[] $secrets The signing secrets (whsec_...) to verify against. A request signed with any of them is accepted.
	 */
	public function __construct( $secrets ) {
		$this->secrets = array_filter( array_map( 'trim', $secrets ) );
	}

	/**
	 * Whether there is at least one signing secret to verify against.
	 *
	 * @return bool
	 */
	public function has_secrets() {
		return ! empty( $this->secrets );
	}

	/**
	 * Verify a webhook request.
	 *
	 * @param string $webhook_id The webhook-id header.
	 * @param string $timestamp  The webhook-timestamp header.
	 * @param string $signatures The webhook-signature header, one or more space-delimited "v1,<signature>" values.
	 * @param string $body       The raw request body.
	 *
	 * @return true|\WP_Error True if the request is authentic, otherwise a WP_Error.
	 */
	public function verify( $webhook_id, $timestamp, $signatures, $body ) {
		if ( empty( $webhook_id ) || empty( $timestamp ) || empty( $signatures ) ) {
			return new \WP_Error( 'missing_headers', 'The webhook headers are missing.' );
		}

		if ( ! ctype_digit( (string) $timestamp ) || abs( time() - (int) $timestamp ) > self::TIMESTAMP_TOLERANCE ) {
			return new \WP_Error( 'invalid_timestamp', 'The webhook timestamp is outside the allowed tolerance.' );
		}

		$signed_content = "{$webhook_id}.{$timestamp}.{$body}";

		// The header holds several signatures while a secret is being rotated.
		$provided = array();
		foreach ( explode( ' ', $signatures ) as $versioned_signature ) {
			$parts = explode( ',', $versioned_signature, 2 );
			if ( 2 === count( $parts ) && 'v1' === $parts[0] ) {
				$provided[] = $parts[1];
			}
		}

		foreach ( $this->secrets as $secret ) {
			$key = $this->decode_secret( $secret );
			if ( false === $key ) {
				continue;
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The signature is base64 encoded by specification.
			$expected = base64_encode( hash_hmac( 'sha256', $signed_content, $key, true ) );
			foreach ( $provided as $signature ) {
				if ( hash_equals( $expected, $signature ) ) {
					return true;
				}
			}
		}

		return new \WP_Error( 'invalid_signature', 'The webhook signature is invalid.' );
	}

	/**
	 * Decode a signing secret to the raw HMAC key.
	 *
	 * @param string $secret The signing secret, in the format whsec_<base64-encoded-secret>.
	 *
	 * @return string|false The raw key, or false if the secret could not be decoded.
	 */
	protected function decode_secret( $secret ) {
		if ( 0 === strpos( $secret, 'whsec_' ) ) {
			$secret = substr( $secret, strlen( 'whsec_' ) );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The secret is base64 encoded by specification.
		$key = base64_decode( $secret, true );

		return empty( $key ) ? false : $key;
	}
}
