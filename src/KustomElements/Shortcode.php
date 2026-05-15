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
 *   [kustom_element data-key="my-key"]
 *   [kustom_element data-key="my-key" data-purchase-amount="9900"]
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
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'data-key'             => '',
				'data-purchase-amount' => '',
			),
			$atts,
			'kustom_element'
		);

		$data_key = sanitize_text_field( $atts['data-key'] );
		if ( empty( $data_key ) ) {
			return '';
		}

		Scripts::enqueue();

		$purchase_amount = sanitize_text_field( $atts['data-purchase-amount'] );

		ob_start();

		/**
		 * Fires before the Kustom Element is rendered via shortcode.
		 *
		 * @since 2.21.0
		 *
		 * @param string $data_key        The element data-key.
		 * @param string $purchase_amount Purchase amount in minor units (empty string if not set).
		 */
		do_action( 'kco_before_kustom_element_shortcode', $data_key, $purchase_amount );

		echo '<kustom-payment-method-display';
		echo ' data-key="' . esc_attr( $data_key ) . '"';
		if ( '' !== $purchase_amount ) {
			echo ' data-purchase-amount="' . esc_attr( $purchase_amount ) . '"';
		}
		echo '></kustom-payment-method-display>' . "\n";

		/**
		 * Fires after the Kustom Element is rendered via shortcode.
		 *
		 * @since 2.21.0
		 *
		 * @param string $data_key        The element data-key.
		 * @param string $purchase_amount Purchase amount in minor units (empty string if not set).
		 */
		do_action( 'kco_after_kustom_element_shortcode', $data_key, $purchase_amount );

		return ob_get_clean();
	}
}
