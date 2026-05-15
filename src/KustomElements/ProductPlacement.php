<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ProductPlacement class for Kustom Elements.
 *
 * Renders a <kustom-payment-method-display> element on single product pages
 * at the configured hook and priority.
 */
class ProductPlacement {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( 'yes' !== Settings::get( 'ke_product_enabled', 'no' ) ) {
			return;
		}

		$hook     = Settings::get( 'ke_product_hook', 'woocommerce_single_product_summary' );
		$priority = (int) Settings::get( 'ke_product_priority', 25 );

		add_action( $hook, array( $this, 'render' ), $priority );
	}

	/**
	 * Renders the Kustom Element on the product page.
	 *
	 * @return void
	 */
	public function render() {
		$locale = Settings::get_locale();
		if ( empty( $locale ) ) {
			return;
		}

		Scripts::enqueue();

		$include = Settings::get_include();
		$exclude = Settings::get_exclude();

		/**
		 * Fires before the Kustom Element is rendered on the product page.
		 *
		 * @since 2.21.0
		 *
		 * @param string $locale  The element locale.
		 * @param string $include Comma-separated payment method IDs to include.
		 * @param string $exclude Comma-separated payment method IDs to exclude.
		 */
		do_action( 'kco_before_kustom_element_product', $locale, $include, $exclude );

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
		 * Fires after the Kustom Element is rendered on the product page.
		 *
		 * @since 2.21.0
		 *
		 * @param string $locale  The element locale.
		 * @param string $include Comma-separated payment method IDs to include.
		 * @param string $exclude Comma-separated payment method IDs to exclude.
		 */
		do_action( 'kco_after_kustom_element_product', $locale, $include, $exclude );
	}
}
