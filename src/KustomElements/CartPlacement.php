<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CartPlacement class for Kustom Elements.
 *
 * Renders a <kustom-payment-method-display> element on the cart page
 * at the configured hook and priority.
 */
class CartPlacement {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( 'yes' !== Settings::get( 'ke_cart_enabled', 'no' ) ) {
			return;
		}

		$hook     = Settings::get( 'ke_cart_hook', 'woocommerce_cart_totals_after_order_total' );
		$priority = (int) Settings::get( 'ke_cart_priority', 10 );

		add_action( $hook, array( $this, 'render' ), $priority );
	}

	/**
	 * Renders the Kustom Element on the cart page.
	 *
	 * @return void
	 */
	public function render() {
		$locale = Settings::get( 'ke_locale', Settings::default_locale() );
		if ( empty( $locale ) ) {
			return;
		}

		Scripts::enqueue();

		$include = Settings::get( 'ke_include', '' );
		$exclude = Settings::get( 'ke_exclude', '' );

		/**
		 * Fires before the Kustom Element is rendered on the cart page.
		 *
		 * @since 2.21.0
		 *
		 * @param string $locale  The element locale.
		 * @param string $include Comma-separated payment method IDs to include.
		 * @param string $exclude Comma-separated payment method IDs to exclude.
		 */
		do_action( 'kco_before_kustom_element_cart', $locale, $include, $exclude );

		echo '<kustom-payment-method-display';
		echo ' locale="' . esc_attr( $locale ) . '"';
		if ( ! empty( $include ) ) {
			echo ' include="' . esc_attr( $include ) . '"';
		}
		if ( ! empty( $exclude ) ) {
			echo ' exclude="' . esc_attr( $exclude ) . '"';
		}
		echo '></kustom-payment-method-display>' . "\n";

		/**
		 * Fires after the Kustom Element is rendered on the cart page.
		 *
		 * @since 2.21.0
		 *
		 * @param string $locale  The element locale.
		 * @param string $include Comma-separated payment method IDs to include.
		 * @param string $exclude Comma-separated payment method IDs to exclude.
		 */
		do_action( 'kco_after_kustom_element_cart', $locale, $include, $exclude );
	}
}
