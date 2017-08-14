<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_Templates class.
 *
 * Class that registers custom checkout endpoints.
 */
class Klarna_Checkout_For_WooCommerce_Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Confirm endpoint names.
	 *
	 * @var string
	 */
	public static $confirm_endpoint = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_CONFIRM_EP;

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
	 * Plugin actions.
	 */
	public function __construct() {
		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Override template if Klarna Checkout page.
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );
	}

	/**
	 * Register new endpoints.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( self::$confirm_endpoint, EP_PAGES );
	}

	/**
	 * Add new query var.
	 *
	 * @param  array $vars Array of query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::$confirm_endpoint;

		return $vars;
	}

	/**
	 * Override checkout form template if Klarna Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name, $template_path ) {
		global $wp_query;

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( is_checkout() ) {
			// Klarna Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				// Klarna checkout page.
				if ( array_key_exists( 'klarna_checkout_for_woocommerce', $available_gateways ) && 'klarna_checkout_for_woocommerce' === WC()->session->get( 'chosen_payment_method' ) ) {
					$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/klarna-checkout.php';
				}

				// Klarna checkout confirmation page.
				if ( isset( $wp_query->query_vars[ self::$confirm_endpoint ] ) ) {
					$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/klarna-checkout-confirm.php';
				}
			}
		}

		return $template;
	}

	/**
	 * Plugin install action.
	 * Flush rewrite rules to make our custom endpoint available.
	 */
	public static function install() {
		flush_rewrite_rules();
	}

}

Klarna_Checkout_For_WooCommerce_Templates::get_instance();
register_activation_hook( __FILE__, array( 'Klarna_Checkout_For_WooCommerce_Templates', 'install' ) );
