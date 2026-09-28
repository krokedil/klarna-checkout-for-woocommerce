<?php
namespace Krokedil\KustomCheckout\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block class.
 */
class Block {
	/**
	 * The payment method display block name.
	 *
	 * @var string
	 */
	const PAYMENT_BLOCK = 'kustom/payment-element';

	/**
	 * The delivery method display block name.
	 *
	 * @var string
	 */
	const DELIVERY_BLOCK = 'kustom/delivery-element';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register the editor scripts and the blocks.
	 */
	public function register_blocks() {
		$blocks = array(
			'PaymentElement'  => array( $this, 'render_payment_block' ),
			'DeliveryElement' => array( $this, 'render_delivery_block' ),
		);

		foreach ( $blocks as $folder => $render_callback ) {
			$build_name = strtolower( $folder );
			$asset_file = KCO_WC_PLUGIN_PATH . "/blocks/build/{$build_name}.asset.php";
			if ( ! file_exists( $asset_file ) ) {
				continue;
			}

			$assets = include $asset_file;
			wp_register_script(
				"kco-elements-{$build_name}-block",
				plugins_url( "blocks/build/{$build_name}.js", KCO_WC_MAIN_FILE ),
				$assets['dependencies'],
				$assets['version'],
				true
			);
			wp_set_script_translations( "kco-elements-{$build_name}-block", 'klarna-checkout-for-woocommerce' );

			$args = array( 'render_callback' => $render_callback );

			// Load the Kustom Elements SDK wherever the block is used, including the editor canvas, so the preview matches the frontend.
			if ( wp_script_is( Elements::SCRIPT_HANDLE, 'registered' ) ) {
				$args['script_handles'] = array( Elements::SCRIPT_HANDLE );
			}

			register_block_type( KCO_WC_PLUGIN_PATH . "/blocks/src/{$folder}", $args );
		}
	}

	/**
	 * Render callback for the Kustom Payment Element block.
	 *
	 * @param array $attributes The block attributes.
	 * @return string
	 */
	public function render_payment_block( $attributes ) {
		return $this->render_block( Utility::render_payment_element( $this->get_element_atts( $attributes ) ) );
	}

	/**
	 * Render callback for the Kustom Delivery Element block.
	 *
	 * @param array $attributes The block attributes.
	 * @return string
	 */
	public function render_delivery_block( $attributes ) {
		return $this->render_block( Utility::render_delivery_element( $this->get_element_atts( $attributes ) ) );
	}

	/**
	 * Map the block attributes to the element atts supported by the shortcodes.
	 *
	 * @param array $attributes The block attributes.
	 * @return array
	 */
	private function get_element_atts( $attributes ) {
		return array(
			'include' => sanitize_text_field( $attributes['include'] ?? '' ),
			'exclude' => sanitize_text_field( $attributes['exclude'] ?? '' ),
		);
	}

	/**
	 * Wrap the element markup in the block wrapper, so block supports such as margin and padding apply.
	 *
	 * @param string $element The escaped element markup.
	 * @return string
	 */
	private function render_block( $element ) {
		if ( empty( Utility::get_public_api_key() ) ) {
			// Only explain the missing key in the editor preview, never to shoppers.
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				return '';
			}

			$element = sprintf(
				'<p>%s</p>',
				esc_html__( 'Add a Kustom Elements public API key in the Kustom Checkout settings to display this element.', 'klarna-checkout-for-woocommerce' )
			);
		}

		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $element );
	}
}
