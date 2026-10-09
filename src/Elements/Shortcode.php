<?php
namespace Krokedil\KustomCheckout\Elements;

use Krokedil\KustomCheckout\Express\Express;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode class.
 *
 * Registers the [kustom_payment_element], [kustom_delivery_element] and [kustom_express_element] shortcodes.
 */
class Shortcode {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_shortcode( 'kustom_payment_element', array( $this, 'payment_element' ) );
		add_shortcode( 'kustom_delivery_element', array( $this, 'delivery_element' ) );
		add_shortcode( 'kustom_express_element', array( $this, 'express_element' ) );
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

	/**
	 * [kustom_express_element] shortcode callback.
	 *
	 * @param array $atts Shortcode attributes. The context is auto (product on a product page, else cart), cart or product.
	 * @return string
	 */
	public function express_element( $atts ) {
		$atts = shortcode_atts(
			array(
				'locale'  => '',
				'context' => 'auto',
			),
			$atts,
			'kustom_express_element'
		);

		$context = Express::resolve_context( sanitize_key( $atts['context'] ) );

		return $context ? Express::render( $context, $atts ) : '';
	}
}
