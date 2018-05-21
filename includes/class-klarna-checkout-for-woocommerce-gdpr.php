<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Compliance with European Union's General Data Protection Regulation.
 *
 * @class    Klarna_Checkout_For_Woocommerce_GDPR
 * @version  1.0.0
 * @package  Klarna_Checkout/Classes
 * @category Class
 * @author   Krokedil
 */
class Klarna_Checkout_For_Woocommerce_GDPR {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'privacy_declarations' ) );
	}
	/**
	 * Privacy declarations.
	 *
	 * @return void
	 */
	public function privacy_declarations() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$content =
				__(
					'When you place an order in the webstore with Klarna Checkout as the choosen payment method, ' .
					'information about the products in the order (namne, price, quantity, SKU) is sent to Klarna. ' .
					'When the purchase is finalized Klarna sends your billing and shipping address back to the webstore. ' .
					'This data plus an unique identifier for the purchase is then stored as billing and shipping data in the order in WooCommerce.',
					'klarna-checkout-for-woocommerce'
				);
			wp_add_privacy_policy_content(
				'Klarna Checkout for WooCommerce',
				wp_kses_post( wpautop( $content ) )
			);
		}
	}
}
new Klarna_Checkout_For_Woocommerce_GDPR();
