<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks class for Kustom Elements.
 *
 * Registers two static Gutenberg content blocks that print a Kustom Elements
 * web component into post content:
 *   - kco/payment-method-display  -> <kustom-payment-method-display>
 *   - kco/delivery-method-display -> <kustom-delivery-method-display>
 *
 * The blocks are static (save() writes the tag in JS, no render_callback). The
 * editor bundles are built by @wordpress/scripts to blocks/build/. The loader
 * script that wakes the components up is injected separately by Scripts.php.
 */
class Blocks {

	/**
	 * Block definitions.
	 *
	 * `entry` matches the webpack entry/output name (the lower-cased source
	 * folder name), used to locate the built JS and its asset manifest.
	 *
	 * @var array<int,array<string,string>>
	 */
	private const BLOCKS = array(
		array(
			'name'   => 'kco/payment-method-display',
			'handle' => 'kco-payment-method-display-block',
			'entry'  => 'paymentmethoddisplay',
		),
		array(
			'name'   => 'kco/delivery-method-display',
			'handle' => 'kco-delivery-method-display-block',
			'entry'  => 'deliverymethoddisplay',
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers both static blocks and their editor scripts.
	 *
	 * @return void
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$data = array(
			// Always resolves to a value; required as the web components' locale attribute.
			'locale'  => Settings::get_locale(),
			'logoUrl' => KCO_WC_PLUGIN_URL . '/assets/img/kustom_logo_primary.png',
		);

		foreach ( self::BLOCKS as $block ) {
			$build_path = '/blocks/build/' . $block['entry'];
			$asset_file = KCO_WC_PLUGIN_PATH . $build_path . '.asset.php';

			$dependencies = array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' );
			$version      = KCO_WC_VERSION;

			if ( file_exists( $asset_file ) ) {
				$asset        = include $asset_file;
				$dependencies = $asset['dependencies'] ?? $dependencies;
				$version      = $asset['version'] ?? $version;
			}

			wp_register_script(
				$block['handle'],
				KCO_WC_PLUGIN_URL . $build_path . '.js',
				$dependencies,
				$version,
				true
			);

			wp_localize_script( $block['handle'], 'kcoKustomElements', $data );

			register_block_type(
				$block['name'],
				array(
					'editor_script' => $block['handle'],
				)
			);
		}
	}
}
