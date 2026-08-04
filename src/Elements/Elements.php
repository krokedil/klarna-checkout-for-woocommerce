<?php
namespace Krokedil\KustomCheckout\Elements;

use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elements class.
 *
 * Orchestrates the Kustom Elements payment method display and delivery method display
 * web components: settings, placement hooks, shortcodes and the Elements SDK script.
 */
class Elements {
	/**
	 * The Elements settings.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * The Elements shortcodes.
	 *
	 * @var Shortcode
	 */
	public $shortcode;

	/**
	 * The public API key used for the currently enqueued script, if any.
	 *
	 * @var string
	 */
	private $public_api_key = '';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Register settings, shortcodes and placement/enqueue hooks.
	 */
	public function init() {
		$this->settings  = new Settings();
		$this->shortcode = new Shortcode();

		$payment_product_position  = SettingsUtility::get_setting( 'elements_payment_product_position', '' );
		$payment_cart_position     = SettingsUtility::get_setting( 'elements_payment_cart_position', '' );
		$shipping_product_position = SettingsUtility::get_setting( 'elements_shipping_product_position', '' );
		$shipping_cart_position    = SettingsUtility::get_setting( 'elements_shipping_cart_position', '' );

		if ( ! empty( $payment_product_position ) ) {
			add_action( $payment_product_position, array( $this, 'render_payment_element' ) );
		}

		if ( ! empty( $payment_cart_position ) ) {
			add_action( $payment_cart_position, array( $this, 'render_payment_element' ) );
		}

		if ( ! empty( $shipping_product_position ) ) {
			add_action( $shipping_product_position, array( $this, 'render_shipping_element' ) );
		}

		if ( ! empty( $shipping_cart_position ) ) {
			add_action( $shipping_cart_position, array( $this, 'render_shipping_element' ) );
		}

		if ( wc_string_to_bool( SettingsUtility::get_setting( 'elements_payment_footer', 'no' ) ) ) {
			add_action( 'wp_footer', array( $this, 'render_payment_element' ) );
		}

		if ( wc_string_to_bool( SettingsUtility::get_setting( 'elements_shipping_footer', 'no' ) ) ) {
			add_action( 'wp_footer', array( $this, 'render_shipping_element' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Echo the payment method display element. Used as a hook callback.
	 */
	public function render_payment_element() {
		echo Utility::render_payment_element(); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in Utility::render_payment_element().
	}

	/**
	 * Echo the delivery method display element. Used as a hook callback.
	 */
	public function render_shipping_element() {
		echo Utility::render_shipping_element(); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in Utility::render_shipping_element().
	}

	/**
	 * Whether any Elements web component is set to render on the current request.
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( apply_filters( 'kco_elements_show_everywhere', false ) ) {
			return true;
		}

		if ( wc_string_to_bool( SettingsUtility::get_setting( 'elements_payment_footer', 'no' ) )
			|| wc_string_to_bool( SettingsUtility::get_setting( 'elements_shipping_footer', 'no' ) )
		) {
			return true;
		}

		global $post;
		if ( $post instanceof \WP_Post
			&& ( has_shortcode( $post->post_content, 'kustom_payment_element' ) || has_shortcode( $post->post_content, 'kustom_shipping_element' ) )
		) {
			return true;
		}

		if ( is_product()
			&& ( SettingsUtility::get_setting( 'elements_payment_product_position', '' ) || SettingsUtility::get_setting( 'elements_shipping_product_position', '' ) )
		) {
			return true;
		}

		if ( is_cart()
			&& ( SettingsUtility::get_setting( 'elements_payment_cart_position', '' ) || SettingsUtility::get_setting( 'elements_shipping_cart_position', '' ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Register and enqueue the Kustom Elements SDK script, if Elements is active on this request.
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_active() ) {
			return;
		}

		$testmode = SettingsUtility::is_testmode();

		$public_api_key = $testmode
			? SettingsUtility::get_setting( 'elements_playground_public_api_key', '' )
			: SettingsUtility::get_setting( 'elements_live_public_api_key', '' );

		if ( empty( $public_api_key ) ) {
			return;
		}

		$this->public_api_key = $public_api_key;

		$default_src = $testmode
			? 'https://js.playground.kustom.co/kustom-elements/v1/pre-load.js'
			: 'https://js.live.kustom.co/kustom-elements/v1/pre-load.js';
		$src         = apply_filters( 'kco_elements_script_src', $default_src, $testmode );

		wp_register_script( 'kustom-elements', $src, array(), KCO_WC_VERSION, false );
		add_filter( 'script_loader_tag', array( $this, 'add_script_attributes' ), 10, 2 );
		wp_add_inline_script( 'kustom-elements', $this->get_init_script(), 'after' );
		wp_enqueue_script( 'kustom-elements' );
	}

	/**
	 * Add the `async`, `id` and `data-public-api-key` attributes to the Kustom Elements script tag.
	 *
	 * @param string $tag    The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @return string
	 */
	public function add_script_attributes( $tag, $handle ) {
		if ( 'kustom-elements' !== $handle ) {
			return $tag;
		}

		$tag = str_replace( ' src', ' async src', $tag );
		$tag = str_replace(
			'></script>',
			sprintf( " id='kustom-elements-script' data-public-api-key='%s'></script>", esc_attr( $this->public_api_key ) ),
			$tag
		);

		return $tag;
	}

	/**
	 * The Kustom Elements installation snippet's initialization wrapper.
	 *
	 * @return string
	 */
	private function get_init_script() {
		return '(function(w){((window.kustomElements=window.kustomElements||function(w,...n){return new Promise((o,i)=>{window.kustomElements._internal.q.push({method:w,args:n,resolve:o,reject:i});});}),(window.kustomElements._internal=window.kustomElements._internal||{q:[],snippetVersion:"1.0.0"}),window.kustomElements.load||(window.kustomElements.load=new Promise((w,n)=>{((window.kustomElements._internal.loadResolve=w),(window.kustomElements._internal.loadReject=n));})));})(window);';
	}
}
