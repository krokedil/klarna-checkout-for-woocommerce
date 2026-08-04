<?php
namespace Krokedil\KustomCheckout\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class.
 *
 * Shared helpers for rendering Kustom Elements web components.
 */
class Utility {
	/**
	 * Get the 5-character locale (language-COUNTRY) to use for Elements.
	 *
	 * @return string
	 */
	public static function get_locale() {
		$locale = substr( str_replace( '_', '-', get_locale() ), 0, 5 );

		return apply_filters( 'kco_elements_locale', $locale );
	}

	/**
	 * Render the payment method display element.
	 *
	 * @param array $atts Optional atts (locale, include, exclude).
	 * @return string
	 */
	public static function render_payment_element( $atts = array() ) {
		return self::render_element( 'kustom-payment-method-display', $atts );
	}

	/**
	 * Render the delivery method display element.
	 *
	 * @param array $atts Optional atts (locale, include, exclude).
	 * @return string
	 */
	public static function render_shipping_element( $atts = array() ) {
		return self::render_element( 'kustom-delivery-method-display', $atts );
	}

	/**
	 * Build the markup for a Kustom Elements custom element tag.
	 *
	 * @param string $tag  The custom element tag name.
	 * @param array  $atts Optional atts (locale, include, exclude).
	 * @return string
	 */
	private static function render_element( $tag, $atts ) {
		$locale = empty( $atts['locale'] ) ? self::get_locale() : $atts['locale'];

		$html = sprintf( '<%1$s locale="%2$s"', esc_attr( $tag ), esc_attr( $locale ) );

		foreach ( array( 'include', 'exclude' ) as $key ) {
			if ( ! empty( $atts[ $key ] ) ) {
				$html .= sprintf( ' %1$s="%2$s"', esc_attr( $key ), esc_attr( $atts[ $key ] ) );
			}
		}

		$html .= sprintf( '></%1$s>', esc_attr( $tag ) );

		return $html;
	}
}
