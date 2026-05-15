<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CartPlacement class for Kustom Elements.
 *
 * Renders a <kustom-payment-method-display> element on the cart page
 * at the configured hook and priority.
 */
class CartPlacement {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( 'yes' !== Settings::get( 'ke_cart_enabled', 'no' ) ) {
			return;
		}

		$hook     = Settings::get( 'ke_cart_hook', 'woocommerce_cart_totals_after_order_total' );
		$priority = (int) Settings::get( 'ke_cart_priority', 10 );

		add_action( $hook, array( $this, 'render' ), $priority );
	}

	/**
	 * Renders the Kustom Element on the cart page.
	 *
	 * @return void
	 */
	public function render() {
		$data_key = Settings::get( 'ke_cart_data_key', '' );
		if ( empty( $data_key ) ) {
			return;
		}

		Scripts::enqueue();

		$purchase_amount = '';
		if ( WC()->cart ) {
			// Kustom expects the amount in minor units (e.g. öre/cents).
			$purchase_amount = (string) (int) ( (float) WC()->cart->get_total( 'edit' ) * 100 );
		}

		/**
		 * Fires before the Kustom Element is rendered on the cart page.
		 *
		 * @since 2.21.0
		 *
		 * @param string $data_key        The element data-key.
		 * @param string $purchase_amount Purchase amount in minor units.
		 */
		do_action( 'kco_before_kustom_element_cart', $data_key, $purchase_amount );

		echo '<kustom-payment-method-display';
		echo ' data-key="' . esc_attr( $data_key ) . '"';
		if ( '' !== $purchase_amount ) {
			echo ' data-purchase-amount="' . esc_attr( $purchase_amount ) . '"';
		}
		echo '></kustom-payment-method-display>' . "\n";

		/**
		 * Fires after the Kustom Element is rendered on the cart page.
		 *
		 * @since 2.21.0
		 *
		 * @param string $data_key        The element data-key.
		 * @param string $purchase_amount Purchase amount in minor units.
		 */
		do_action( 'kco_after_kustom_element_cart', $data_key, $purchase_amount );
	}
}
