<?php
/**
 * GDPR compliance class file.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Compliance with European Union's General Data Protection Regulation.
 */
class KCO_GDPR {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'privacy_declarations' ) );
		add_action( 'init', array( $this, 'maybe_add_privacy_policy_text' ) );
	}
	/**
	 * Privacy declarations.
	 *
	 * @return void
	 */
	public function privacy_declarations() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			// @codingStandardsIgnoreStart
			$content =
				__(
					'When you place an order in the webstore with Klarna Checkout as the choosen payment method, ' .
					'information about the products in the order (name, price, quantity, SKU) is sent to Klarna. ' .
					'When the purchase is finalized Klarna sends your billing and shipping address back to the webstore. ' .
					'This data plus an unique identifier for the purchase is then stored as billing and shipping data in the order in WooCommerce.',
					'klarna-checkout-for-woocommerce'
				);
			// @codingStandardsIgnoreEnd
			wp_add_privacy_policy_content(
				__( 'Klarna Checkout for WooCommerce', 'klarna-checkout-for-woocommerce' ),
				wp_kses_post( wpautop( $content ) )
			);
		}
	}

	/**
	 * Maybe adds the terms checkbox to the checkout.
	 *
	 * @return void
	 */
	public function maybe_add_privacy_policy_text() {
		$settings                    = get_option( 'woocommerce_kco_settings' );
		$display_privacy_policy_text = ( isset( $settings['display_privacy_policy_text'] ) ) ? $settings['display_privacy_policy_text'] : 'no';

		if ( 'above' === $display_privacy_policy_text ) {
			add_action( 'kco_wc_before_snippet', array( $this, 'kco_wc_display_privacy_policy_text' ) );
		} elseif ( 'below' === $display_privacy_policy_text ) {
			add_action( 'kco_wc_after_snippet', array( $this, 'kco_wc_display_privacy_policy_text' ) );
		}
	}

	/**
	 * Gets the terms template.
	 *
	 * @return void
	 */
	public function kco_wc_display_privacy_policy_text() {
		if ( function_exists( 'wc_checkout_privacy_policy_text' ) ) {
			echo wp_kses_post( wc_checkout_privacy_policy_text() );
		}
	}
}
new KCO_GDPR();
