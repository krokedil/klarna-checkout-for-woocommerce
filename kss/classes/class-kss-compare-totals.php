<?php // phpcs:ignore
/**
 * Free orders class
 *
 * @package KlarnaShippingService/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free Orders class
 */
class KSS_Compare_Totals {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'kco_wc_process_payment', array( $this, 'compare_kco_kss_totals' ), 10, 2 );
	}

	/**
	 * Kompare KCO and KSA order shipping totals for uncaptured amounts and warn the customer.
	 *
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $klarna_order The Kustom order.
	 * @return void
	 */
	public function compare_kco_kss_totals( $order_id, $klarna_order ) {

		$order = wc_get_order( $order_id );

		$klarna_total_amount = round( $klarna_order['selected_shipping_option']['price'] / 100, 2 );
		$order_total_amount  = ( $order->get_shipping_total() + $order->get_shipping_tax() );

		$dif = $klarna_total_amount - $order_total_amount;

		if ( $dif > 3 || $dif < -3 ) {
			$order->add_order_note( sprintf( __( 'A discrepancy between the Kustom order\'s shipping tax and the WooCommerce shipping tax has been detected. Please verify that you have set up your %1$s and %2$s according to the instructions given in the plugin documentation  ', 'klarna-checkout-for-woocommerce' ), ( '<a href="https://docs.krokedil.com/klarna-for-woocommerce/additional-klarna-plugins/klarna-shipping-assistant/#configuration" target="_blank">configuration</a>' ), ( '<a href="https://docs.krokedil.com/klarna-for-woocommerce/additional-klarna-plugins/klarna-shipping-assistant/#tax-settings" target="_blank">tax settings</a>' ) ) );
		}
	}
}

new KSS_Compare_Totals();
