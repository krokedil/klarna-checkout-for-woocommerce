<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KustomElements module.
 *
 * Bootstraps all Kustom Elements sub-classes: settings, script injection,
 * product/cart page placements, shortcode, and Gutenberg block.
 */
class KustomElements {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Scripts instance.
	 *
	 * @var Scripts
	 */
	public $scripts;

	/**
	 * Product page placement instance.
	 *
	 * @var ProductPlacement
	 */
	public $product_placement;

	/**
	 * Cart page placement instance.
	 *
	 * @var CartPlacement
	 */
	public $cart_placement;

	/**
	 * Shortcode instance.
	 *
	 * @var Shortcode
	 */
	public $shortcode;

	/**
	 * Block instance.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initializes the module.
	 *
	 * Settings and Block are always loaded so the admin section and block
	 * are available regardless of the enabled toggle. The remaining classes
	 * that render front-end output are only loaded when the feature is active.
	 *
	 * @return void
	 */
	public function init() {
		// Always register settings (admin) and the block type.
		$this->settings = new Settings();
		$this->block    = new Block();

		if ( ! Settings::is_enabled() ) {
			return;
		}

		$this->scripts          = new Scripts();
		$this->product_placement = new ProductPlacement();
		$this->cart_placement   = new CartPlacement();
		$this->shortcode        = new Shortcode();
	}
}
