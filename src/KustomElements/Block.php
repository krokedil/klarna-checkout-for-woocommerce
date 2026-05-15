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
					'locale'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'include' => array(
						'type'    => 'string',
						'default' => '',
					),
					'exclude' => array(
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
	 * Falls back to global KE settings when block attributes are not set.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$locale = sanitize_text_field( $attributes['locale'] ?? '' );
		if ( empty( $locale ) ) {
			$locale = Settings::get_locale();
		}

		if ( empty( $locale ) ) {
			return '';
		}

		Scripts::enqueue();

		$include = sanitize_text_field( ! empty( $attributes['include'] ) ? $attributes['include'] : Settings::get_include() );
		$exclude = sanitize_text_field( ! empty( $attributes['exclude'] ) ? $attributes['exclude'] : Settings::get_exclude() );

		$html  = '<kustom-payment-method-display';
		$html .= ' locale="' . esc_attr( $locale ) . '"';
		if ( ! empty( $include ) ) {
			$html .= ' include="' . esc_attr( $include ) . '"';
		}
		if ( ! empty( $exclude ) ) {
			$html .= ' exclude="' . esc_attr( $exclude ) . '"';
		}
		$html .= '></kustom-payment-method-display>';

		return $html;
	}
}
