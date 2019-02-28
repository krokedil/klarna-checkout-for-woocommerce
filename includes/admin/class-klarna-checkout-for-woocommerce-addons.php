<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Addons class.
 *
 * Handles Klarna Checkout addons page.
 */
class Klarna_Checkout_For_WooCommerce_Addons {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Klarna_Checkout_For_WooCommerce_Confirmation constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_css' ) );
	}

	/**
	 * Load Admin CSS
	 **/
	function enqueue_css( $hook ) {
		if ( 'woocommerce_page_checkout-addons' == $hook || 'settings_page_specter-admin' == $hook ) {
			wp_register_style( 'klarna-checkout-addons', KCO_WC_PLUGIN_URL . '/assets/css/checkout-addons.css', false, KCO_WC_VERSION );
			wp_enqueue_style( 'klarna-checkout-addons' );
		}
	}

	/**
	 * Add the Addons menu to WooCommerce
	 **/
	public function add_menu() {
		$submenu = add_submenu_page( 'woocommerce', __( 'Checkout addons', 'klarna-checkout-for-woocommerce' ), __( 'Checkout addons', 'klarna-checkout-for-woocommerce' ), 'manage_woocommerce', 'checkout-addons', array( $this, 'options_page' ) );
	}

	/**
	 * Add the Addons options page to WooCommerce.
	 **/
	public function options_page() {
		?>
		<div id="checkout-addon-heading" class="checkout-addon-heading">
			<h1><?php esc_html_e( 'Checkout Addons', 'klarna-checkout-for-woocommerce' ); ?></h1>
		</div>	
		<div id="checkout-addons" class="wrap">
			<div class="list">
				<div class="checkout-addon">
					<h3 class="checkout-addon-title">My addon</h3>
					<p class="checkout-addon-excerpt">A short text to describe the addon.</p>
					<div class="checkout-addon-footer">
						<div class="inside-wrapper">
							<span class="checkout-addon-status">Status: <span>Not installed</span></span>
							<span class="checkout-addon-action"><a class="button install" href="#">Install</a></span>
						</div>
					</div>
				</div>
				
				<div class="checkout-addon">
					<h3 class="checkout-addon-title">My addon</h3>
					<p class="checkout-addon-excerpt">A short text to describe the addon.</p>
					<div class="checkout-addon-footer">
						<div class="inside-wrapper">
							<span class="checkout-addon-status">Status: <span>Not installed</span></span>
							<span class="checkout-addon-action"><a class="button install" href="#">Install</a></span>
						</div>
					</div>
				</div>
				
				<div class="checkout-addon">
					<h3 class="checkout-addon-title">My addon</h3>
					<p class="checkout-addon-excerpt">A short text to describe the addon.</p>
					<div class="checkout-addon-footer">
						<div class="inside-wrapper">
							<span class="checkout-addon-status">Status: <span>Not installed</span></span>
							<span class="checkout-addon-action"><a class="button install" href="#">Install</a></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}



}

Klarna_Checkout_For_WooCommerce_Addons::get_instance();
