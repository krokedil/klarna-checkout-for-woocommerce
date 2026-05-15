<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scripts class for Kustom Elements.
 *
 * Outputs the merchant-provided Elements script tag in <head> on pages
 * where at least one Kustom Element is rendered.
 */
class Scripts {

	/**
	 * Whether the script has been output this request.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Whether any element has been enqueued on this page load.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Priority 1 so the script lands before any element tag rendered in the body.
		add_action( 'wp_head', array( $this, 'maybe_print_script' ), 1 );
	}

	/**
	 * Signal that a Kustom Element will be rendered on this page.
	 *
	 * Called by placement classes and the shortcode before rendering an element.
	 *
	 * @return void
	 */
	public static function enqueue() {
		self::$enqueued = true;
	}

	/**
	 * Outputs the sanitized Elements script tag if an element has been flagged.
	 *
	 * Because wp_head fires before most body content, placements hook at
	 * priority ≥ 10 on their respective action, while this fires at priority 1.
	 * To ensure the script is present even when the shortcode is the first signal,
	 * we also late-print via wp_footer as fallback (see constructor).
	 *
	 * @return void
	 */
	public function maybe_print_script() {
		if ( self::$printed ) {
			return;
		}

		// For shortcode and block usage we can't know at wp_head time whether
		// the page contains an element, so we always print when product/cart
		// placements are active, and rely on the shortcode/block to call enqueue()
		// before wp_footer fires.
		$product_active = 'yes' === Settings::get( 'ke_product_enabled', 'no' ) && is_product();
		$cart_active    = 'yes' === Settings::get( 'ke_cart_enabled', 'no' ) && is_cart();

		if ( ! $product_active && ! $cart_active ) {
			// Defer to footer so shortcode/block pages still get the script.
			add_action( 'wp_footer', array( $this, 'maybe_print_script_footer' ), 1 );
			return;
		}

		$this->print_script();
	}

	/**
	 * Fallback print in wp_footer for pages using the shortcode or block.
	 *
	 * @return void
	 */
	public function maybe_print_script_footer() {
		if ( self::$printed || ! self::$enqueued ) {
			return;
		}
		$this->print_script();
	}

	/**
	 * Sanitizes and outputs the script tag.
	 *
	 * Only a <script> element with safe attributes is allowed through.
	 *
	 * @return void
	 */
	private function print_script() {
		$raw = Settings::get( 'ke_script', '' );
		if ( empty( trim( $raw ) ) ) {
			return;
		}

		$allowed = array(
			'script' => array(
				'src'   => true,
				'type'  => true,
				'async' => true,
				'defer' => true,
				'id'    => true,
			),
		);

		$safe = wp_kses( $raw, $allowed );
		if ( empty( trim( $safe ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized above via wp_kses.
		echo $safe . "\n";
		self::$printed = true;
	}
}
