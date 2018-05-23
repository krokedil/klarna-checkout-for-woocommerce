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
		add_action( 'init', array( $this, 'maybe_add_privacy_policy_text' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'maybe_add_checkbox' ) );
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

	/**
	 * Maybe adds the terms checkbox to the checkout.
	 *
	 * @return void
	 */
	public function maybe_add_privacy_policy_text() {
		$settings                    = get_option( 'woocommerce_kco_settings' );
		$display_privacy_policy_text = $settings['display_privacy_policy_text'];

		if ( 'above' == $display_privacy_policy_text ) {
			add_action( 'kco_wc_before_snippet', array( $this, 'kco_wc_display_privacy_policy_text' ) );
		} elseif ( 'below' == $display_privacy_policy_text ) {
			add_action( 'kco_wc_after_snippet', array( $this, 'kco_wc_display_privacy_policy_text' ) );
		}
	}

	/**
	 * Gets the terms template.
	 *
	 * @return void
	 */
	public function kco_wc_display_privacy_policy_text() {
		echo wc_checkout_privacy_policy_text();
	}

	/**
	 * Maybe adds a checkbox to the Klarna iFrame.
	 *
	 * @param array $args The arguments array for the Klarna request.
	 * @return array $args The arguments array for the Klarna request.
	 */
	public function maybe_add_checkbox( $args ) {
		$settings     = get_option( 'woocommerce_kco_settings' );
		$add_checkbox = $settings['add_terms_and_conditions_checkbox'];
		if ( 'yes' === $add_checkbox && wc_terms_and_conditions_checkbox_enabled() ) {
			$args['options']['additional_checkbox']['text']     = wc_replace_policy_page_link_placeholders( wc_get_terms_and_conditions_checkbox_text() );
			$args['options']['additional_checkbox']['checked']  = false;
			$args['options']['additional_checkbox']['required'] = true;
		}
		return $args;
	}
}
new Klarna_Checkout_For_Woocommerce_GDPR();
