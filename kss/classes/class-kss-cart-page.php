<?php // phpcs:ignore
/**
 * Kustom tags class
 *
 * @package KlarnaShippingService/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kustom tags class
 */
class KSS_Cart_Page {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'wc_get_template', array( $this, 'override_shipping_template' ), 999, 2 );
	}

	/**
	 * Overrides the default cart shipping template.
	 *
	 * @param string $template The absolute template path.
	 * @param string $template_name The name of the template.
	 * @return string
	 */
	public function override_shipping_template( $template, $template_name ) {
		// If its not the cart, return.
		if ( ! is_cart() ) {
			return $template;
		}

		// If its not the cart/cart-shipping.php file, return.
		if ( 'cart/cart-shipping.php' !== $template_name ) {
			return $template;
		}

		if ( locate_template( 'woocommerce/kss-cart-shipping.php' ) ) {
			$template = locate_template( 'woocommerce/kss-cart-shipping.php' );
		} else {
			$template = KLARNA_KSS_PATH . '/templates/kss-cart-shipping.php';
		}

		return $template;
	}
} new KSS_Cart_Page();
