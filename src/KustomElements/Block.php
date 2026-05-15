<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block class for Kustom Elements.
 *
 * Registers a server-side rendered Gutenberg block (kco/kustom-element)
 * that renders a <kustom-payment-method-display> web component.
 * No separate JS build is required — the block editor UI is registered inline.
 */
class Block {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Registers the block type with the editor script and server-side render callback.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'kco-kustom-element-block',
			KCO_WC_PLUGIN_URL . '/assets/js/kustom-element-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			KCO_WC_VERSION,
			true
		);

		register_block_type(
			'kco/kustom-element',
			array(
				'editor_script'   => 'kco-kustom-element-block',
				'attributes'      => array(
					'dataKey'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'purchaseAmount' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server-side render callback for the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$data_key = sanitize_text_field( $attributes['dataKey'] ?? '' );
		if ( empty( $data_key ) ) {
			return '';
		}

		Scripts::enqueue();

		$purchase_amount = sanitize_text_field( $attributes['purchaseAmount'] ?? '' );

		$html  = '<kustom-payment-method-display';
		$html .= ' data-key="' . esc_attr( $data_key ) . '"';
		if ( '' !== $purchase_amount ) {
			$html .= ' data-purchase-amount="' . esc_attr( $purchase_amount ) . '"';
		}
		$html .= '></kustom-payment-method-display>';

		return $html;
	}
}
