<?php
namespace Krokedil\KustomCheckout\Utility;

/**
 * Class SigningKeyUtility
 *
 * Helper class to generate and validate signing keys used in the callbacks or redirects from Kustom.
 */
class SigningKeyUtility {
	/**
	 * Generate a signing key using the Kustom order id stored in the WooCommerce session.
	 *
	 * @return string|null The generated signing key, or null if the Kustom order ID is not available in the session.
	 */
	public static function from_session_kco_id() {
		// Ensure the WooCommerce session is available.
		if ( ! WC()->session ) {
			return null;
		}

		$kustom_order_id = WC()->session->get( 'kco_wc_order_id' );

		if ( ! $kustom_order_id ) {
			return null;
		}

		return self::generate( $kustom_order_id );
	}

	/**
	 * Generate a signing key using the Kustom order id stored in the WooCommerce order.
	 *
	 * @param \WC_Order|int|null $order The WooCommerce order object, id or null.
	 *
	 * @return string|null The generated signing key, or null if the Kustom order ID is not available in the order.
	 */
	public static function from_wc_order_kco_id( $order ) {
		$order = wc_get_order( $order );

		// Ensure we have a valid order object.
		if ( ! $order ) {
			return null;
		}

		$kustom_order_id = $order->get_meta( '_wc_klarna_order_id' );

		if ( ! $kustom_order_id ) {
			return null;
		}

		return self::generate( $kustom_order_id );
	}

	/**
	 * Validate a signing key from the Kustom order.
	 *
	 * @param array  $kustom_order The Kustom order data, which should contain the signing key in the 'merchant_data' field.
	 * @param string $value The value that was signed, typically the Kustom order ID.
	 *
	 * @return bool True if the signing key is valid, false otherwise.
	 */
	public static function validate_from_kustom_order( $kustom_order, $value ) {
		if ( ! isset( $kustom_order['merchant_data'] ) ) {
			return false;
		}

		$merchant_data = json_decode( $kustom_order['merchant_data'], true );

		if ( ! isset( $merchant_data['signing_key'] ) ) {
			return false;
		}

		return self::validate( $value, $merchant_data['signing_key'] );
	}

	/**
	 * Generate a signing key based on the provided parameters.
	 *
	 * @param string $value The value to be signed, typically something unique for the order, like the order ID.
	 *
	 * @return string The generated signing key.
	 */
	public static function generate( $value ) {
		return hash_hmac( 'sha256', $value, self::get_secret_key() );
	}

	/**
	 * Validate a given signing key against the expected value.
	 *
	 * @param string $value The value that was signed, typically the order ID.
	 * @param string $provided_key The signing key provided in the callback or redirect.
	 *
	 * @return bool True if the provided signing key is valid, false otherwise.
	 */
	public static function validate( $value, $provided_key ) {
		return hash_equals( self::generate( $value ), $provided_key );
	}

	/**
	 * Get the secret key using the WordPress salt function, which provides a unique and secure key for signing.
	 *
	 * @return string The secret key used for generating signing keys.
	 */
	private static function get_secret_key() {
		return wp_salt( 'auth' );
	}
}
