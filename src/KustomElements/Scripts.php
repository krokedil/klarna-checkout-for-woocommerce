<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scripts class for Kustom Elements.
 *
 * Outputs the Kustom Elements loader snippet in <head> on pages where at
 * least one element is rendered. The snippet is built from the merchant's
 * API key and the script URL; no raw HTML needs to be pasted by the merchant.
 *
 * The snippet mirrors the one from the Kustom Portal:
 *   1. An async external <script> that loads the pre-load.js file.
 *   2. An inline <script> that initialises the kustomElements queue object.
 */
class Scripts {

	/**
	 * Whether the snippet has already been printed this request.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Whether any element has signalled it will render on this page.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'maybe_print_script' ), 1 );
	}

	/**
	 * Signals that a Kustom Element will be rendered on this page.
	 *
	 * Called by placement classes and the shortcode before rendering an element.
	 *
	 * @return void
	 */
	public static function enqueue() {
		self::$enqueued = true;
	}

	/**
	 * Outputs the snippet if a product/cart placement is active.
	 *
	 * For shortcode/block pages we cannot know at wp_head time whether the
	 * page contains an element, so we register a wp_footer fallback and let
	 * the shortcode/block call enqueue() before that fires.
	 *
	 * @return void
	 */
	public function maybe_print_script() {
		if ( self::$printed ) {
			return;
		}

		$product_active = 'yes' === Settings::get( 'ke_product_enabled', 'no' ) && is_product();
		$cart_active    = 'yes' === Settings::get( 'ke_cart_enabled', 'no' ) && is_cart();

		if ( ! $product_active && ! $cart_active ) {
			add_action( 'wp_footer', array( $this, 'maybe_print_script_footer' ), 1 );
			return;
		}

		$this->print_snippet();
	}

	/**
	 * Fallback print in wp_footer for pages that use the shortcode or block.
	 *
	 * @return void
	 */
	public function maybe_print_script_footer() {
		if ( self::$printed || ! self::$enqueued ) {
			return;
		}
		$this->print_snippet();
	}

	/**
	 * Builds and outputs the Kustom Elements loader snippet.
	 *
	 * @return void
	 */
	private function print_snippet() {
		$api_key = Settings::get_api_key();
		if ( empty( $api_key ) ) {
			return;
		}

		$script_url = Settings::get_script_url();

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- all values are escaped inline below.
		echo "\n<!-- Kustom Elements -->\n";

		// 1. External pre-load script.
		echo '<script';
		echo ' async';
		echo ' id="kustom-elements-script"';
		echo ' type="text/javascript"';
		echo ' src="' . esc_url( $script_url ) . '"';
		echo ' data-public-api-key="' . esc_attr( $api_key ) . '"';
		echo '></script>' . "\n";

		// 2. Inline queue initialiser — static snippet, no user data interpolated.
		echo '<script>(function(w){';
		echo 'window.kustomElements=window.kustomElements||function(w,...n){return new Promise(((o,i)=>{window.kustomElements._internal.q.push({method:w,args:n,resolve:o,reject:i})}))},';
		echo 'window.kustomElements._internal=window.kustomElements._internal||{q:[],snippetVersion:"1.0.0"},';
		echo 'window.kustomElements.load||(window.kustomElements.load=new Promise(((w,n)=>{window.kustomElements._internal.loadResolve=w,window.kustomElements._internal.loadReject=n})));';
		echo '})(window);</script>' . "\n";

		echo "<!-- /Kustom Elements -->\n";
		// phpcs:enable

		self::$printed = true;
	}
}
