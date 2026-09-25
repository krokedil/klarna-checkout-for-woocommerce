<?php
namespace Krokedil\KustomCheckout\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode class.
 *
 * Registers the [kustom_payment_element] and [kustom_delivery_element] shortcodes.
 */
class Shortcode {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_shortcode( 'kustom_payment_element', array( $this, 'payment_element' ) );
		add_shortcode( 'kustom_delivery_element', array( $this, 'delivery_element' ) );
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
	 * [kustom_delivery_element] shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function delivery_element( $atts ) {
		$atts = shortcode_atts(
			array(
				'locale'  => '',
				'include' => '',
				'exclude' => '',
			),
			$atts,
			'kustom_delivery_element'
		);

		return Utility::render_delivery_element( $atts );
	}
}
