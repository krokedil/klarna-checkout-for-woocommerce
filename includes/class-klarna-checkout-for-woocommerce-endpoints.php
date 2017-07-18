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
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'kco';

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Override template if Klarna Checkout page
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );

		// Inserting your new tab/page into the My Account page.
		add_action( 'woocommerce_account_' . self::$endpoint . '_endpoint', array( $this, 'endpoint_content' ) );
	}

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( self::$endpoint, EP_PAGES );
	}

	/**
	 * Add new query var.
	 *
	 * @param  array $vars Array of query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::$endpoint;

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

		if ( is_checkout() && isset( $wp_query->query_vars[ self::$endpoint ] ) ) {
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/form-checkout.php';
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
new Klarna_Checkout_For_WooCommerce_Endpoints();
