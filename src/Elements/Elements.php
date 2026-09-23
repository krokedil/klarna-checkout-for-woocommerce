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
 * web components: settings, placement hooks, shortcodes, blocks and the Elements SDK script.
 */
class Elements {
	/**
	 * The Kustom Elements SDK script handle.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'kustom-elements';

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
	 * The Elements blocks.
	 *
	 * @var Block
	 */
	public $block;

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
	 * Register settings, shortcodes, blocks and placement/enqueue hooks.
	 */
	public function init() {
		$this->settings  = new Settings();
		$this->shortcode = new Shortcode();
		$this->block     = new Block();

		add_action( 'init', array( $this, 'register_scripts' ), 5 );
		add_action( 'init', array( $this, 'add_placements' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Attach the elements to the product and cart page placements from the settings.
	 *
	 * Runs on init, since reading the settings loads translated defaults.
	 */
	public function add_placements() {
		$this->add_product_placement( SettingsUtility::get_setting( 'elements_payment_product_position', '' ), 'render_payment_element' );
		$this->add_cart_placement( SettingsUtility::get_setting( 'elements_payment_cart_position', '' ), 'render_payment_element' );
		$this->add_product_placement( SettingsUtility::get_setting( 'elements_shipping_product_position', '' ), 'render_delivery_element' );
		$this->add_cart_placement( SettingsUtility::get_setting( 'elements_shipping_cart_position', '' ), 'render_delivery_element' );
	}

	/**
	 * Attach a render callback to the product page, at the priority stored in the placement setting.
	 *
	 * @param string $priority The placement setting value (a priority on the product summary hook).
	 * @param string $callback The render method on this class.
	 */
	private function add_product_placement( $priority, $callback ) {
		if ( ! in_array( (string) $priority, Settings::PRODUCT_PRIORITIES, true ) ) {
			return;
		}

		add_action( Settings::PRODUCT_HOOK, array( $this, $callback ), absint( $priority ) );
	}

	/**
	 * Attach a render callback to the cart page hook stored in the placement setting.
	 *
	 * @param string $hook     The placement setting value (a cart hook name).
	 * @param string $callback The render method on this class.
	 */
	private function add_cart_placement( $hook, $callback ) {
		if ( ! in_array( (string) $hook, Settings::CART_HOOKS, true ) ) {
			return;
		}

		add_action( $hook, array( $this, $callback ), 5 );
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
	public function render_delivery_element() {
		echo Utility::render_delivery_element(); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in Utility::render_delivery_element().
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

		global $post;
		if ( $post instanceof \WP_Post
			&& ( has_shortcode( $post->post_content, 'kustom_payment_element' )
				|| has_shortcode( $post->post_content, 'kustom_delivery_element' )
				|| has_block( Block::PAYMENT_BLOCK, $post )
				|| has_block( Block::DELIVERY_BLOCK, $post ) )
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
	 * Register the Kustom Elements SDK script, if a public API key is set. Registered on init so the blocks can use it
	 * both in the editor and on the frontend.
	 */
	public function register_scripts() {
		$public_api_key = Utility::get_public_api_key();
		if ( empty( $public_api_key ) ) {
			return;
		}

		$this->public_api_key = $public_api_key;

		$testmode    = SettingsUtility::is_testmode();
		$default_src = $testmode
			? 'https://js.playground.kustom.co/kustom-elements/v1/pre-load.js'
			: 'https://js.live.kustom.co/kustom-elements/v1/pre-load.js';
		$src         = apply_filters( 'kco_elements_script_src', $default_src, $testmode );

		wp_register_script( self::SCRIPT_HANDLE, $src, array(), KCO_WC_VERSION, false );
		add_filter( 'script_loader_tag', array( $this, 'add_script_attributes' ), 10, 2 );
		wp_add_inline_script( self::SCRIPT_HANDLE, $this->get_init_script(), 'after' );
	}

	/**
	 * Enqueue the Kustom Elements SDK script, if Elements is active on this request.
	 */
	public function enqueue_scripts() {
		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) || ! $this->is_active() ) {
			return;
		}

		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Add the `async`, `id` and `data-public-api-key` attributes to the Kustom Elements script tag.
	 *
	 * @param string $tag    The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @return string
	 */
	public function add_script_attributes( $tag, $handle ) {
		if ( self::SCRIPT_HANDLE !== $handle ) {
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
