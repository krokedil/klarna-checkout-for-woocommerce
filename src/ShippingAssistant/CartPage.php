<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart page class.
 *
 * Replaces the default cart shipping row with a simplified, non-interactive one for customers using
 * Kustom Checkout, since the actual shipping method selection happens inside the Kustom iframe at
 * checkout rather than on the cart page.
 */
class CartPage {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'wc_get_template', array( $this, 'override_shipping_template' ), 999, 2 );
	}

	/**
	 * Overrides the default cart shipping template.
	 *
	 * Only for customers who have Kustom Checkout selected (or already flagged as TMS-controlled for this
	 * session) — otherwise this would replace the shipping row for every payment method, on every store
	 * that enables Kustom Shipping Assistant.
	 *
	 * @param string $template The absolute template path.
	 * @param string $template_name The name of the template.
	 * @return string
	 */
	public function override_shipping_template( $template, $template_name ) {
		if ( ! is_cart() ) {
			return $template;
		}

		if ( 'cart/cart-shipping.php' !== $template_name ) {
			return $template;
		}

		if ( ! $this->kco_shipping_is_active() ) {
			return $template;
		}

		if ( locate_template( 'woocommerce/kss-cart-shipping.php' ) ) {
			$template = locate_template( 'woocommerce/kss-cart-shipping.php' );
		} else {
			$template = KCO_WC_PLUGIN_PATH . '/templates/kss-cart-shipping.php';
		}

		return $template;
	}

	/**
	 * Whether the current session has Kustom Checkout as the chosen (or TMS-flagged) payment method.
	 *
	 * @return bool
	 */
	private function kco_shipping_is_active() {
		if ( null === WC()->session ) {
			return false;
		}

		return 'kco' === WC()->session->get( 'chosen_payment_method' ) || (bool) WC()->session->get( 'kco_kss_enabled' );
	}
}
