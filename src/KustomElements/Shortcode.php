<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode class for Kustom Elements.
 *
 * Registers the [kustom_element] shortcode for free placement of a
 * <kustom-payment-method-display> web component anywhere on the site.
 *
 * Usage:
 *   [kustom_element]
 *   [kustom_element locale="sv-SE"]
 *   [kustom_element locale="sv-SE" include="klarna_pay_later" exclude="klarna_pay_now"]
 */
class Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'kustom_element', array( $this, 'render' ) );
	}

	/**
	 * Renders the shortcode output.
	 *
	 * Locale falls back to the global KE setting when not provided.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'locale'  => Settings::get_locale(),
				'include' => Settings::get_include(),
				'exclude' => Settings::get_exclude(),
			),
			$atts,
			'kustom_element'
		);

		$locale = sanitize_text_field( $atts['locale'] );
		if ( empty( $locale ) ) {
			return '';
		}

		Scripts::enqueue();

		$include = sanitize_text_field( $atts['include'] );
		$exclude = sanitize_text_field( $atts['exclude'] );

		ob_start();

		/**
		 * Fires before the Kustom Element is rendered via shortcode.
		 *
		 * @since 2.21.0
		 *
		 * @param string $locale  The element locale.
		 * @param string $include Comma-separated payment method IDs to include.
		 * @param string $exclude Comma-separated payment method IDs to exclude.
		 */
		do_action( 'kco_before_kustom_element_shortcode', $locale, $include, $exclude );

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
		 * Fires after the Kustom Element is rendered via shortcode.
		 *
		 * @since 2.21.0
		 *
		 * @param string $locale  The element locale.
		 * @param string $include Comma-separated payment method IDs to include.
		 * @param string $exclude Comma-separated payment method IDs to exclude.
		 */
		do_action( 'kco_after_kustom_element_shortcode', $locale, $include, $exclude );

		return ob_get_clean();
	}
}
