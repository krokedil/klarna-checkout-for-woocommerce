<?php
namespace Krokedil\KustomCheckout\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode class.
 *
 * Registers the [kustom_payment_element] and [kustom_shipping_element] shortcodes.
 */
class Shortcode {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_shortcode( 'kustom_payment_element', array( $this, 'payment_element' ) );
		add_shortcode( 'kustom_shipping_element', array( $this, 'shipping_element' ) );
	}

	/**
	 * [kustom_payment_element] shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function payment_element( $atts ) {
		$atts = shortcode_atts(
			array(
				'locale'  => '',
				'include' => '',
				'exclude' => '',
			),
			$atts,
			'kustom_payment_element'
		);

		return Utility::render_payment_element( $atts );
	}

	/**
	 * [kustom_shipping_element] shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shipping_element( $atts ) {
		$atts = shortcode_atts(
			array(
				'locale'  => '',
				'include' => '',
				'exclude' => '',
			),
			$atts,
			'kustom_shipping_element'
		);

		return Utility::render_shipping_element( $atts );
	}
}
