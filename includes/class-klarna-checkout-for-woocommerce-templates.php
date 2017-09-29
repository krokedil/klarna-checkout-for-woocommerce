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
		// Override template if Klarna Checkout page.
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );

		add_filter( 'the_title', array( $this, 'confirm_page_title' ) );
	}

	/**
	 * Filter Checkout page title in confirmation page.
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function confirm_page_title( $title ) {
		if ( ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_checkout() && isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] ) {
			$title = 'Please wait while we process your order.';
			remove_filter( 'the_title', array( $this, 'confirm_page_title' ) );
		}

		return $title;
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
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( is_checkout() ) {
			// Klarna Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				// Klarna checkout page.
				if ( array_key_exists( 'klarna_checkout_for_woocommerce', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'klarna_checkout_for_woocommerce' === WC()->session->get( 'chosen_payment_method' ) ) {
						$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/klarna-checkout.php';
					}

					// If chosen payment method does not exist and KCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );
						if ( 'klarna_checkout_for_woocommerce' === key( $available_gateways ) ) {
							$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/klarna-checkout.php';
						}
					}
				}

				// Klarna checkout confirmation page.
				if ( isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] && isset( $_GET['kco_wc_order_id'] ) ) {
					if ( WC()->session->get( 'kco_wc_order_id' ) === $_GET['kco_wc_order_id'] ) {
						$template = KLARNA_CHECKOUT_FOR_WOOCOMMERCE_PLUGIN_PATH . '/templates/klarna-checkout-confirm.php';
					}
				}
			}
		}

		return $template;
	}

}

Klarna_Checkout_For_WooCommerce_Templates::get_instance();
