<?php
namespace Krokedil\KustomCheckout\Express;

use Krokedil\KustomCheckout\Blocks\Api\Controllers\OrderController;
use Krokedil\KustomCheckout\Elements\Elements;
use Krokedil\KustomCheckout\Elements\Settings as ElementsSettings;
use Krokedil\KustomCheckout\Elements\Utility as ElementsUtility;
use Krokedil\KustomCheckout\ShippingAssistant\Settings as ShippingAssistantSettings;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Express class.
 *
 * Kustom Elements express buttons: one-click wallet purchases from the product page, cart and any page
 * with the shortcode or block. The block cart and checkout get them through ExpressPaymentMethod.
 */
class Express {
	/**
	 * Express context: buy the shopper's cart.
	 *
	 * @var string
	 */
	const CONTEXT_CART = 'cart';

	/**
	 * Express context: buy one product, leaving the shopper's cart untouched.
	 *
	 * @var string
	 */
	const CONTEXT_PRODUCT = 'product';

	/**
	 * The order meta marking an order placed with an express button.
	 *
	 * @var string
	 */
	const ORDER_META = '_kco_express';

	/**
	 * The product placement setting value that renders the button through the shortcode or block only.
	 *
	 * @var string
	 */
	const PLACEMENT_MANUAL = 'manual';

	/**
	 * The frontend script handle for the shortcode, block and page placements.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'kco-express';

	/**
	 * The classic cart placement priority on woocommerce_proceed_to_checkout, after the checkout button.
	 *
	 * @var int
	 */
	const CART_PRIORITY = 30;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_placements' ) );
		add_action( 'init', array( $this, 'register_scripts' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'woocommerce_should_clear_cart_after_payment', array( $this, 'maybe_keep_cart_after_payment' ) );
		add_action( 'kco_wc_confirm_klarna_order', array( $this, 'maybe_destroy_express_session' ) );
	}

	/**
	 * Whether express buttons may be shown: the gateway is enabled, KSA is enabled and the Elements key is set.
	 *
	 * @return bool
	 */
	public static function is_available() {
		$available = 'yes' === SettingsUtility::get_setting( 'enabled', 'no' )
			&& ShippingAssistantSettings::is_enabled()
			&& ! empty( ElementsUtility::get_public_api_key() );

		/**
		 * Filters whether the Kustom express buttons are available.
		 *
		 * @param bool $available Whether the gateway, Kustom Shipping Assistant and an Elements public API key are all set up.
		 */
		return (bool) apply_filters( 'kco_express_is_available', $available );
	}

	/**
	 * Whether product express is enabled, either at a product page placement or for manual placement.
	 *
	 * @return bool
	 */
	public static function is_product_express_enabled() {
		return self::PLACEMENT_MANUAL === self::get_product_position() || null !== self::get_product_priority();
	}

	/**
	 * The product page priority from the placement setting, or null when it is not placed automatically.
	 *
	 * @return int|null
	 */
	private static function get_product_priority() {
		$position = self::get_product_position();

		return in_array( $position, ElementsSettings::PRODUCT_PRIORITIES, true ) ? absint( $position ) : null;
	}

	/**
	 * The product placement setting value.
	 *
	 * @return string
	 */
	private static function get_product_position() {
		return (string) SettingsUtility::get_setting( 'elements_express_product_position', '' );
	}

	/**
	 * The message shown to the shopper when the express order could not be created.
	 *
	 * @return string
	 */
	public static function get_error_message() {
		return __( 'Could not start express checkout, please try again or use checkout.', 'klarna-checkout-for-woocommerce' );
	}

	/**
	 * Attach the product page and classic cart placements.
	 */
	public function add_placements() {
		if ( ! self::is_available() ) {
			return;
		}

		$priority = self::get_product_priority();
		if ( null !== $priority ) {
			add_action( ElementsSettings::PRODUCT_HOOK, array( $this, 'render_product_placement' ), $priority );
		}

		if ( self::is_product_express_enabled() ) {
			add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_amounts' ), 10, 3 );
		}

		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_cart_placement' ), self::CART_PRIORITY );
	}

	/**
	 * Echo the product express button. Used as a hook callback.
	 */
	public function render_product_placement() {
		echo self::render( self::CONTEXT_PRODUCT ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in render().
	}

	/**
	 * Echo the cart express button. Used as a hook callback.
	 */
	public function render_cart_placement() {
		echo self::render( self::CONTEXT_CART ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in render().
	}

	/**
	 * Resolve the context for the shortcode and block: the product on a product page with product express enabled, else the cart.
	 *
	 * @param string $context The requested context: auto, cart or product.
	 * @return string
	 */
	public static function resolve_context( $context ) {
		if ( self::CONTEXT_CART === $context ) {
			return self::CONTEXT_CART;
		}

		$on_product_page = is_product() && self::is_product_express_enabled();
		if ( self::CONTEXT_PRODUCT === $context ) {
			return $on_product_page ? self::CONTEXT_PRODUCT : '';
		}

		return $on_product_page ? self::CONTEXT_PRODUCT : self::CONTEXT_CART;
	}

	/**
	 * Render the express button markup and enqueue its script.
	 *
	 * @param string $context The express context.
	 * @param array  $atts    Optional atts (locale).
	 * @return string
	 */
	public static function render( $context, $atts = array() ) {
		if ( ! self::is_available() ) {
			return '';
		}

		$data = self::CONTEXT_PRODUCT === $context ? self::get_product_data() : self::get_cart_data();
		if ( empty( $data ) ) {
			return '';
		}

		self::enqueue_scripts();

		$locale = empty( $atts['locale'] ) ? ElementsUtility::get_locale() : $atts['locale'];

		return sprintf(
			'<div class="kco-express" data-kco-express="%1$s"><kustom-express-buttons id="kco-express-%2$s" locale="%3$s"></kustom-express-buttons></div>',
			esc_attr( wp_json_encode( $data ) ),
			esc_attr( $context ),
			esc_attr( $locale )
		);
	}

	/**
	 * The button data for the cart context, or an empty array when there is nothing to buy.
	 *
	 * @return array
	 */
	private static function get_cart_data() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return array();
		}

		return array(
			'context'    => self::CONTEXT_CART,
			'orderLines' => self::get_cart_order_lines(),
		);
	}

	/**
	 * The button data for the current product, or an empty array when it cannot be bought this way.
	 *
	 * @return array
	 */
	private static function get_product_data() {
		$product = $GLOBALS['product'] ?? null;
		$product = $product instanceof \WC_Product ? $product : wc_get_product( get_the_ID() );
		if ( ! $product || ! $product->is_type( array( 'simple', 'variable' ) ) || ! $product->is_purchasable() ) {
			return array();
		}

		return array_merge(
			array(
				'context'   => self::CONTEXT_PRODUCT,
				'productId' => $product->get_id(),
				'name'      => wp_strip_all_tags( $product->get_name() ),
				'variable'  => $product->is_type( 'variable' ),
			),
			self::get_unit_amounts( $product )
		);
	}

	/**
	 * The unit price including tax and the unit tax, in minor units.
	 *
	 * @param \WC_Product $product The product or variation.
	 * @return array
	 */
	private static function get_unit_amounts( $product ) {
		$including_tax = (float) wc_get_price_including_tax( $product );
		$excluding_tax = (float) wc_get_price_excluding_tax( $product );

		return array(
			'unitAmount' => (int) round( $including_tax * 100 ),
			'unitTax'    => (int) round( ( $including_tax - $excluding_tax ) * 100 ),
		);
	}

	/**
	 * Add the unit amounts to each variation, so the button can show the selected variation's price.
	 *
	 * @param array                 $data      The variation data.
	 * @param \WC_Product           $product   The parent product.
	 * @param \WC_Product_Variation $variation The variation.
	 * @return array
	 */
	public function add_variation_amounts( $data, $product, $variation ) {
		$data['kco_express'] = self::get_unit_amounts( $variation );

		return $data;
	}

	/**
	 * The cart's order lines as the express element expects them, without shipping since KSA adds it in the sheet.
	 *
	 * @return array
	 */
	public static function get_cart_order_lines() {
		$cart_data = new \KCO_Request_Cart();
		$cart_data->process_data();

		$order_lines = array();
		foreach ( $cart_data->get_order_lines() as $line ) {
			if ( 'shipping_fee' === ( $line['type'] ?? '' ) ) {
				continue;
			}

			$order_lines[] = array(
				'name'        => wp_strip_all_tags( (string) $line['name'] ),
				'quantity'    => (int) $line['quantity'],
				'totalAmount' => (int) $line['total_amount'],
				'taxAmount'   => (int) $line['total_tax_amount'],
			);
		}

		return $order_lines;
	}

	/**
	 * The frontend configuration shared by the page script and the block express payment method.
	 *
	 * @return array
	 */
	public static function get_script_config() {
		return array(
			'restUrl'   => RestController::get_url(),
			'nonce'     => wp_create_nonce( RestController::NONCE_ACTION ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'locale'    => ElementsUtility::get_locale(),
			'currency'  => strtolower( get_woocommerce_currency() ),
			'sdk'       => array(
				'src'          => Elements::get_script_src(),
				'publicApiKey' => ElementsUtility::get_public_api_key(),
			),
			'i18n'      => array(
				'error' => self::get_error_message(),
			),
		);
	}

	/**
	 * Register the frontend script and its styles.
	 */
	public function register_scripts() {
		$asset_file = KCO_WC_PLUGIN_PATH . '/blocks/build/expressbuttons.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$assets = include $asset_file;
		wp_register_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'blocks/build/expressbuttons.js', KCO_WC_MAIN_FILE ),
			$assets['dependencies'],
			$assets['version'],
			true
		);

		wp_register_style( self::SCRIPT_HANDLE, false, array(), KCO_WC_VERSION );
		wp_add_inline_style( self::SCRIPT_HANDLE, self::get_inline_style() );
	}

	/**
	 * The styles for the button container: spacing, and the disabled state before a variation is chosen.
	 *
	 * @return string
	 */
	public static function get_inline_style() {
		return '.kco-express{margin:1em 0;clear:both}.kco-express[data-disabled="true"]{opacity:.5;pointer-events:none}';
	}

	/**
	 * Enqueue the frontend script and its configuration, once per page.
	 */
	private static function enqueue_scripts() {
		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) || wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script( self::SCRIPT_HANDLE, 'window.kcoExpressParams = ' . wp_json_encode( self::get_script_config() ) . ';', 'before' );
		wp_enqueue_script( self::SCRIPT_HANDLE );
		wp_enqueue_style( self::SCRIPT_HANDLE );
	}

	/**
	 * Register the createOrder route, and the validation route when the block checkout has not already done so.
	 */
	public function register_routes() {
		( new RestController() )->register_routes();

		if ( ! BlocksUtility::is_checkout_block_enabled() ) {
			( new OrderController() )->register_routes();
		}
	}

	/**
	 * Keep the shopper's cart on the order received page of a product express order, which never held it.
	 *
	 * @param bool $should_clear Whether WooCommerce is about to empty the cart.
	 * @return bool
	 */
	public function maybe_keep_cart_after_payment( $should_clear ) {
		global $wp;

		if ( ! $should_clear || empty( $wp->query_vars['order-received'] ) ) {
			return $should_clear;
		}

		$order = wc_get_order( absint( $wp->query_vars['order-received'] ) );

		return ! ( $order && self::is_product_express_order( $order ) );
	}

	/**
	 * Delete the product express session once its purchase is confirmed, as only the shopper's own cart should remain.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function maybe_destroy_express_session( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::is_product_express_order( $order ) || ! WC()->session ) {
			return;
		}

		ExpressSession::destroy( (string) WC()->session->get( OrderCreator::EXPRESS_SESSION_KEY ) );
		WC()->session->__unset( OrderCreator::EXPRESS_SESSION_KEY );
	}

	/**
	 * Whether the order was placed with a product express button.
	 *
	 * @param \WC_Order $order The order.
	 * @return bool
	 */
	public static function is_product_express_order( $order ) {
		return self::CONTEXT_PRODUCT === $order->get_meta( self::ORDER_META );
	}
}
