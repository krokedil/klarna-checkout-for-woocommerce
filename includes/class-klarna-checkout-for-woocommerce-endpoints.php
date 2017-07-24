<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_Endpoints class.
 *
 * Class that registers custom checkout endpoints.
 */
class Klarna_Checkout_For_WooCommerce_Endpoints {

	/**
	 * Checkout endpoint name.
	 *
	 * @var string
	 */
	public static $checkout_endpoint = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_CHECKOUT_EP;

	/**
	 * Confirm endpoint names.
	 *
	 * @var string
	 */
	public static $confirm_endpoint = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_CONFIRM_EP;

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the checkout page title.
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );

		// Override template if Klarna Checkout page.
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );

		// Change checkout URL if KCO is currently selected method.
		// add_filter( 'woocommerce_get_checkout_url', array( $this, 'maybe_filter_checkout_url' ) );
	}

	/**
	 * Register new endpoints.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( self::$checkout_endpoint, EP_PAGES );
		add_rewrite_endpoint( self::$confirm_endpoint, EP_PAGES );
	}

	/**
	 * Add new query var.
	 *
	 * @param  array $vars Array of query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::$checkout_endpoint;
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

		// Klarna Checkout.
		if ( 'checkout/form-checkout.php' === $template_name ) {
			// Klarna checkout page.
			if ( is_checkout() && isset( $wp_query->query_vars[ self::$checkout_endpoint ] ) ) {
				$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/klarna-checkout.php';
			}

			// Klarna checkout confirmation page.
			if ( is_checkout() && isset( $wp_query->query_vars[ self::$confirm_endpoint ] ) ) {
				$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/klarna-checkout-confirm.php';
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

	/**
	 * Change checkout page title, if Klarna Checkout.
	 *
	 * @param  string $title Page title.
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ self::$checkout_endpoint ] );

		if ( $is_endpoint && is_checkout() ) {
			$title = __( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' );
		}

		return $title;
	}

	/**
	 * Maybe filter checkout URL to go to KCO page.
	 *
	 * @param  string $url Checkout page URL.
	 * @return mixed
	 */
	public function maybe_filter_checkout_url( $url ) {
		// If KCO is chosen payment method.
		if ( 'klarna_checkout_for_woocommerce' === WC()->session->get( 'chosen_payment_method' ) ) {
			$url = wc_get_checkout_url() . '/kco';
		}

		return $url;
	}

}
new Klarna_Checkout_For_WooCommerce_Endpoints();

register_activation_hook( __FILE__, array( 'Klarna_Checkout_For_WooCommerce_Endpoints', 'install' ) );
