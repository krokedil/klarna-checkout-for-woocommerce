<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strips the shortcode checkout down to what a walk-in sale has: nothing required, and
 * no shipping.
 */
class Checkout {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'relax_checkout_fields' ), 100 );
		add_filter( 'woocommerce_cart_needs_shipping', array( $this, 'skip_shipping' ), 100 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( $this, 'skip_shipping' ), 100 );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'fall_back_to_the_store_email' ), 10, 2 );
	}

	/**
	 * Drop the shipping fields and make every billing field optional.
	 *
	 * @param array $fields The checkout fields.
	 * @return array
	 */
	public function relax_checkout_fields( $fields ) {
		if ( ! self::is_chosen() ) {
			return $fields;
		}

		unset( $fields['shipping'] );

		foreach ( array_keys( $fields['billing'] ?? array() ) as $key ) {
			$fields['billing'][ $key ]['required'] = false;
		}

		return $fields;
	}

	/**
	 * Switch shipping off for an over-the-counter sale.
	 *
	 * @param bool $needs_shipping Whether the cart needs shipping.
	 * @return bool
	 */
	public function skip_shipping( $needs_shipping ) {
		return self::is_chosen() ? false : $needs_shipping;
	}

	/**
	 * Prefill the billing email, because Woo emails, receipts and the order list all
	 * assume there is one.
	 *
	 * @param string|null $value The value WooCommerce resolved.
	 * @param string      $key The checkout field key.
	 * @return string|null
	 */
	public function fall_back_to_the_store_email( $value, $key ) {
		if ( 'billing_email' !== $key || ! empty( $value ) || ! self::is_chosen() ) {
			return $value;
		}

		return self::fallback_email();
	}

	/**
	 * The email an in-person order is filed under when the customer gives none.
	 *
	 * @return string
	 */
	public static function fallback_email() {
		/**
		 * The billing email an in-person order gets when the customer gives none.
		 *
		 * WooCommerce emails the receipt to it, so a store may prefer a counter inbox
		 * over the administrator's.
		 *
		 * @since 2.22.0
		 *
		 * @param string $email The fallback email. Default the site's admin email.
		 */
		return apply_filters( 'kco_ipp_fallback_billing_email', get_option( 'admin_email' ) );
	}

	/**
	 * Whether in-person payment is the method being checked out with.
	 *
	 * @return bool
	 */
	public static function is_chosen() {
		// A shopper can put any method id in the session, so the capability decides too.
		if ( ! current_user_can( Gateway::required_capability() ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads which method is selected; WooCommerce verifies its own nonce before acting on it.
		$posted = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : '';

		if ( '' !== $posted ) {
			return Gateway::ID === $posted;
		}

		return WC()->session instanceof \WC_Session && Gateway::ID === WC()->session->get( 'chosen_payment_method' );
	}
}
