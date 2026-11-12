<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strips the shortcode checkout down to what a walk-in sale has: nothing required, no
 * shipping, and no address on the order.
 */
class Checkout {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'relax_checkout_fields' ), 100 );
		add_filter( 'woocommerce_cart_needs_shipping', array( $this, 'skip_shipping' ), 100 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( $this, 'skip_shipping' ), 100 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'remove_address' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Leave the customer and the address off an in-person order: the salesperson is logged
	 * in, and the blocked form holds their own prefilled address, not the customer's.
	 *
	 * @param \WC_Order $order The order being created.
	 * @return void
	 */
	public function remove_address( $order ) {
		if ( Gateway::ID === $order->get_payment_method() && current_user_can( Gateway::required_capability() ) ) {
			self::strip_address( $order );
		}
	}

	/**
	 * Make an order a guest order and empty its billing and shipping address. WooCommerce
	 * refuses an order without a billing email and country, so those become the store's own.
	 * Who took the sale is kept in Gateway::STAFF_META.
	 *
	 * @param \WC_Order $order The order.
	 * @return void
	 */
	public static function strip_address( $order ) {
		$order->set_customer_id( 0 );

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			foreach ( array_merge( array_keys( WC()->countries->get_default_address_fields() ), array( 'phone' ) ) as $field ) {
				$setter = "set_{$type}_{$field}";
				if ( is_callable( array( $order, $setter ) ) ) {
					$order->$setter( '' );
				}
			}
		}

		$order->set_billing_email( self::fallback_email() );
		$order->set_billing_country( WC()->countries->get_base_country() );
	}

	/**
	 * Block the address forms of the shortcode checkout while in-person payment is chosen.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() || is_order_received_page() || is_checkout_pay_page() || ! current_user_can( Gateway::required_capability() ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'kco_ipp_checkout_form',
			plugins_url( 'src/InPersonPayments/assets/js/kustom-ipp-checkout-form' . $suffix . '.js', KCO_WC_MAIN_FILE ),
			array( 'jquery', 'jquery-blockui' ),
			KCO_WC_VERSION,
			true
		);
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
		 * @since x.x.x
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
