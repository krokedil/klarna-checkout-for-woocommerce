<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

use Krokedil\KustomCheckout\ShippingAssistant\API\ApiRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kustom Shipping Assistant class.
 *
 * The main class responsible for initializing the Shipping Assistant module, which lets a TMS
 * (transport management system) control shipping methods and pricing inside the Kustom iframe.
 */
class ShippingAssistant {

	/**
	 * Kustom Shipping Assistant settings.
	 *
	 * @var Settings $settings
	 */
	public $settings;

	/**
	 * The API registry instance. Only set when the module is enabled.
	 *
	 * @var ApiRegistry|null
	 */
	protected $api_registry;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init the module at plugins_loaded.
	 */
	public function init() {

		// If the standalone Kustom Shipping Assistant plugin is active, do nothing.
		if ( class_exists( 'Klarna_Shipping_Service_For_WooCommerce' ) ) {

			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-error">
							<p><strong><?php esc_html_e( 'Kustom Shipping Assistant is now included in Kustom Checkout for WooCommerce.', 'klarna-checkout-for-woocommerce' ); ?></strong></p>
							<p><?php esc_html_e( 'Deactivate the separate plugin, then enable it under WooCommerce → Settings → Payments → Kustom Checkout.', 'klarna-checkout-for-woocommerce' ); ?></p>
					</div>
					<?php
				}
			);

			return;
		}

		// The shipping method and its settings are always registered, so shipping zones stay visible and
		// editable regardless of the toggle below. The method itself stays inert (see ShippingMethod::is_available())
		// unless a checkout session has actually enabled it.
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
		$this->settings = new Settings();

		if ( ! Settings::is_enabled() ) {
			return;
		}

		new CartPage();
		new CartItemAttributes();
		new RequestModifier();
		new FreeOrders();
		new CompareTotals();
		new Checkout();
		new MerchantUrls();

		$this->api_registry = new ApiRegistry();
	}

	/**
	 * Register the Kustom Shipping Assistant shipping method.
	 *
	 * @param array $methods WooCommerce shipping methods.
	 * @return array
	 */
	public function add_shipping_method( $methods ) {
		$methods['klarna_kss'] = ShippingMethod::class;
		return $methods;
	}

	/**
	 * Get the instance of the API registry class.
	 *
	 * @return ApiRegistry|null
	 */
	public function api_registry() {
		return $this->api_registry;
	}
}
