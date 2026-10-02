<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compare totals class.
 *
 * Compares the shipping total Kustom charged the customer against what WooCommerce ended up with on the
 * order, and flags the order when they diverge more than a few currency units — usually a sign the TMS's
 * tax settings don't match the store's.
 */
class CompareTotals {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'kco_wc_process_payment', array( $this, 'compare_kco_kss_totals' ), 10, 2 );
	}

	/**
	 * Compare Kustom and Shipping Assistant order shipping totals for uncaptured amounts and warn the merchant.
	 *
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $klarna_order The Kustom order.
	 * @return void
	 */
	public function compare_kco_kss_totals( $order_id, $klarna_order ) {
		if ( ! isset( $klarna_order['selected_shipping_option']['price'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$klarna_total_amount = round( $klarna_order['selected_shipping_option']['price'] / 100, 2 );
		$order_total_amount  = ( $order->get_shipping_total() + $order->get_shipping_tax() );

		$dif = $klarna_total_amount - $order_total_amount;

		if ( $dif > 3 || $dif < -3 ) {
			$order->add_order_note(
				sprintf(
					// translators: 1: configuration docs link, 2: tax settings docs link.
					__( 'A discrepancy between the Kustom order\'s shipping tax and the WooCommerce shipping tax has been detected. Please verify that you have set up your %1$s and %2$s according to the instructions given in the plugin documentation.', 'klarna-checkout-for-woocommerce' ),
					'<a href="https://docs.krokedil.com/kustom-checkout-for-woocommerce/get-started/kustom-shipping-assistant/#configuration" target="_blank">' . esc_html__( 'configuration', 'klarna-checkout-for-woocommerce' ) . '</a>',
					'<a href="https://docs.krokedil.com/kustom-checkout-for-woocommerce/get-started/kustom-shipping-assistant/#tax-settings" target="_blank">' . esc_html__( 'tax settings', 'klarna-checkout-for-woocommerce' ) . '</a>'
				)
			);
		}
	}
}
